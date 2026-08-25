<?php

namespace App\Http\Controllers;

use App\Exports\BkuExport;
use App\Exports\RekapKuartalExport;
use App\Exports\RekapRekeningExport;
use App\Exports\RekapSiplahExport;
use App\Models\AuditLog;
use App\Models\ExportJob;
use App\Models\KasPenutupan;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\Pencairan;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Support\NumberParser;
use App\Support\RealisasiQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $tahunAnggaranAktif = TahunAnggaran::getActive();

        return view('laporan.index', compact('tahunAnggaranAktif'));
    }

    private function streamPdf(\Barryvdh\DomPDF\PDF $pdf, string $filename): \Illuminate\Http\Response
    {
        try {
            return $pdf->stream($filename);
        } catch (\Throwable $e) {
            Log::error('Gagal membuat PDF laporan.', ['exception' => $e->getMessage(), 'file' => $filename]);
            abort(500, 'Gagal membuat PDF. Silakan coba lagi.');
        }
    }

    public function bku(Request $request): \Illuminate\Http\Response|\Illuminate\View\View
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        if ($request->get('cetak') == 'pdf') {
            set_time_limit(0);
            ini_set('memory_limit', -1);
        }
        $bulanVal = $request->input('bulan', date('n'));
        $bulan = is_numeric($bulanVal) ? (int) $bulanVal : (int) date('n');
        $rawTanggalVal = $request->input('tanggal_cetak', '');
        $rawTanggal = is_string($rawTanggalVal) ? $rawTanggalVal : '';
        $tanggalCetak = $rawTanggal !== '' && \Carbon\Carbon::hasFormat($rawTanggal, 'Y-m-d')
            ? \Carbon\Carbon::parse($rawTanggal)->translatedFormat('d F Y')
            : ($rawTanggal ?: \Carbon\Carbon::now()->translatedFormat('d F Y'));
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');
        $profil = PengaturanSekolah::get();

        $transaksis = TransaksiBku::with(
            'rkasItem.program',
            'rkasItem.kodeRekening.jenisBelanja',
            'notaBku.kegiatan',
            'notaBku.kodeRekening.jenisBelanja'
        )
            ->where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $saldoRecord = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', '<', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("SUM(CASE WHEN LOWER(jenis) = 'penerimaan' THEN jumlah ELSE -jumlah END) as saldo")
            ->first();
        $saldoAwal = $saldoRecord ? (float) $saldoRecord->getAttribute('saldo') : 0.0;

        $saldo = $saldoAwal;
        foreach ($transaksis as $t) {
            $saldo += strtolower($t->jenis) == 'penerimaan' ? $t->jumlah : -$t->jumlah;
            $t->saldo_berjalan = $saldo;
        }

        $totals = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'penerimaan' THEN jumlah ELSE 0 END), 0) as total_penerimaan")
            ->selectRaw("COALESCE(SUM(CASE WHEN jenis = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as total_pengeluaran")
            ->firstOrFail();
        $totalPenerimaan = (float) $totals->getAttribute('total_penerimaan');
        $totalPengeluaran = (float) $totals->getAttribute('total_pengeluaran');
        $saldoAkhir = $saldoAwal + $totalPenerimaan - $totalPengeluaran;

        if ($request->get('cetak') == 'pdf') {
            $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
            $bulanLabel = $bulan ? \Carbon\Carbon::createFromDate(null, $bulan, 1)->translatedFormat('F') : '';
            $tahunLabel = $tahunAnggaranAktif->tahun ?? date('Y');
            $pdf = Pdf::loadView('laporan.bku', compact(
                'transaksis', 'profil', 'bulan', 'tahunAnggaranAktif',
                'saldoAwal', 'totalPenerimaan', 'totalPengeluaran', 'saldoAkhir', 'tanggalCetak', 'sumberDanaId'
            ))->setPaper('a4', 'landscape');

            return $this->streamPdf($pdf, 'BKU-' . $namaSekolah . '-' . $bulanLabel . '_' . $tahunLabel . '.pdf');
        }

        return view('laporan.bku', compact(
            'transaksis', 'profil', 'bulan', 'tahunAnggaranAktif',
            'saldoAwal', 'totalPenerimaan', 'totalPengeluaran', 'saldoAkhir', 'tanggalCetak', 'sumberDanaId'
        ));
    }

    public function rekapRekening(Request $request): \Illuminate\Http\Response|\Illuminate\View\View
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        if ($request->get('cetak') == 'pdf') {
            set_time_limit(0);
            ini_set('memory_limit', -1);
        }
        $bulanVal = $request->input('bulan', date('n'));
        $bulan = is_numeric($bulanVal) ? (int) $bulanVal : (int) date('n');
        $rawTanggalVal = $request->input('tanggal_cetak', '');
        $rawTanggal = is_string($rawTanggalVal) ? $rawTanggalVal : '';
        $tanggalCetak = $rawTanggal !== '' && \Carbon\Carbon::hasFormat($rawTanggal, 'Y-m-d')
            ? \Carbon\Carbon::parse($rawTanggal)->translatedFormat('d F Y')
            : ($rawTanggal ?: \Carbon\Carbon::now()->translatedFormat('d F Y'));
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');
        $profil = PengaturanSekolah::get();

        $rkasItems = $this->loadRekapRekeningItems($tahunAnggaranAktif, $bulan);
        $grouped = $rkasItems instanceof \Illuminate\Support\Collection
            ? $rkasItems->groupBy(fn(RkasItem $item): string => $item->kodeRekening->jenisBelanja->nama ?? 'Tidak Terkategori')
            : collect();

        if ($request->input('cetak') == 'pdf') {
            $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
            $bulanLabel = $bulan ? \Carbon\Carbon::createFromDate(null, $bulan, 1)->translatedFormat('F') : '';
            $tahunLabel = $tahunAnggaranAktif->tahun ?? date('Y');
            $pdf = Pdf::loadView('laporan.rekap-rekening', compact(
                'grouped', 'profil', 'bulan', 'tahunAnggaranAktif', 'rkasItems', 'tanggalCetak', 'sumberDanaId'
            ))->setPaper('a4', 'landscape');

            return $this->streamPdf($pdf, 'Rekap_Rekening-' . $namaSekolah . '-' . $bulanLabel . '_' . $tahunLabel . '.pdf');
        }

        return view('laporan.rekap-rekening', compact(
            'grouped', 'profil', 'bulan', 'tahunAnggaranAktif', 'rkasItems', 'tanggalCetak', 'sumberDanaId'
        ));
    }

    public function rekapKuartal(Request $request): \Illuminate\Http\Response|\Illuminate\View\View
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        if ($request->get('cetak') == 'pdf') {
            set_time_limit(0);
            ini_set('memory_limit', -1);
        }
        $bulanVal = $request->input('bulan', date('n'));
        $bulan = is_numeric($bulanVal) ? (int) $bulanVal : (int) date('n');
        $rawTanggalVal = $request->input('tanggal_cetak', '');
        $rawTanggal = is_string($rawTanggalVal) ? $rawTanggalVal : '';
        $tanggalCetak = $rawTanggal !== '' && \Carbon\Carbon::hasFormat($rawTanggal, 'Y-m-d')
            ? \Carbon\Carbon::parse($rawTanggal)->translatedFormat('d F Y')
            : ($rawTanggal ?: \Carbon\Carbon::now()->translatedFormat('d F Y'));
        $kuartal = (int) ceil($bulan / 3);
        $startMonth = ($kuartal - 1) * 3 + 1;
        $bulanMonths = [$startMonth, $startMonth + 1, $startMonth + 2];
        $bulanNames = array_map(
            fn($m) => \Carbon\Carbon::createFromDate(null, $m, 1)->translatedFormat('F'),
            $bulanMonths
        );
        $qLabel = 'Q' . $kuartal;
        $periodeLabel = implode(' s.d. ', $bulanNames);

        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');
        $profil = PengaturanSekolah::get();

        $quarterlyItems = $this->loadKuartalItems($tahunAnggaranAktif, $bulanMonths);
        $grouped = $quarterlyItems instanceof \Illuminate\Support\Collection
            ? $quarterlyItems->groupBy(fn(RkasItem $item): string => $item->kodeRekening->jenisBelanja->nama ?? 'Tidak Terkategori')
            : collect();

        if ($request->get('cetak') == 'pdf') {
            $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
            $tahunLabel = $tahunAnggaranAktif->tahun ?? date('Y');
            $pdf = Pdf::loadView('laporan.rekap-kuartal', compact(
                'grouped', 'profil', 'tahunAnggaranAktif',
                'qLabel', 'periodeLabel', 'bulanMonths', 'bulanNames', 'tanggalCetak', 'sumberDanaId'
            ))->setPaper('a4', 'landscape');

            return $this->streamPdf($pdf, 'Rekap_Kuartal-' . $namaSekolah . '-' . $qLabel . '_' . $tahunLabel . '.pdf');
        }

        return view('laporan.rekap-kuartal', compact(
            'grouped', 'profil', 'tahunAnggaranAktif',
            'qLabel', 'periodeLabel', 'bulanMonths', 'bulanNames', 'kuartal', 'bulan', 'tanggalCetak', 'sumberDanaId'
        ));
    }

    public function rekapSiplah(Request $request): \Illuminate\Http\Response|\Illuminate\View\View
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        if ($request->get('cetak') == 'pdf') {
            set_time_limit(0);
            ini_set('memory_limit', -1);
        }
        $data = $this->prepareRekapSiplahData($request);

        if ($request->get('cetak') == 'pdf') {
            $profil = $data['profil'];
            $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
            $periodeLabel = is_string($data['periodeLabel']) ? $data['periodeLabel'] : '';
            $slug = str_replace([' ', '–'], ['_', '-'], $periodeLabel);
            $tahunAnggaranAktif = $data['tahunAnggaranAktif'];
            $tahunLabel = $tahunAnggaranAktif ? $tahunAnggaranAktif->tahun : date('Y');
            $pdf = Pdf::loadView('laporan.rekap-siplah', $data)->setPaper('a4', 'landscape');

            return $this->streamPdf($pdf, 'Rekap_Siplah-' . $namaSekolah . '-' . $slug . '_' . $tahunLabel . '.pdf');
        }

        return view('laporan.rekap-siplah', $data);
    }

    public function bkuWeb(Request $request): \Illuminate\View\View
    {
        $data = $this->prepareBkuData($request);

        return view('laporan.bku-web', $data);
    }

    public function rekapRekeningWeb(Request $request): \Illuminate\View\View
    {
        $data = $this->prepareRekapRekeningData($request, 50);

        return view('laporan.rekap-rekening-web', $data);
    }

    public function rekapKuartalWeb(Request $request): \Illuminate\View\View
    {
        $data = $this->prepareRekapKuartalData($request, 50);

        return view('laporan.rekap-kuartal-web', $data);
    }

    public function rekapSiplahWeb(Request $request): \Illuminate\View\View
    {
        $data = $this->prepareRekapSiplahData($request);

        return view('laporan.rekap-siplah-web', $data);
    }

    public function bkuExportExcel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        $bulanVal = $request->input('bulan', date('n'));
        $bulan = is_numeric($bulanVal) ? (int) $bulanVal : (int) date('n');
        $profil = PengaturanSekolah::get();
        $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');

        $exportJob = ExportJob::create([
            'user_id' => auth()->id(),
            'type' => 'BKU',
            'status' => 'processing',
        ]);

        $filename = 'bku-bulan-' . $bulan . '-' . $namaSekolah . '.xlsx';

        \App\Jobs\GenerateExportJob::dispatch(
            $exportJob->id,
            \App\Exports\BkuExport::class,
            ['bulan' => $bulan, 'profil' => $namaSekolah, 'tahunAnggaranId' => $tahunAnggaranAktif?->id, 'sumberDanaId' => $sumberDanaId],
            $filename,
        );

        return redirect()->back()->with('info', 'Export BKU sedang diproses. <a href="' . route('exports.download', $exportJob->id) . '" class="font-semibold underline">Cek status</a>.');
    }

    public function rekapRekeningExportExcel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        $bulanVal = $request->input('bulan', date('n'));
        $bulan = is_numeric($bulanVal) ? (int) $bulanVal : (int) date('n');
        $profil = PengaturanSekolah::get();
        $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');

        $exportJob = ExportJob::create([
            'user_id' => auth()->id(),
            'type' => 'Rekap Realisasi',
            'status' => 'processing',
        ]);

        $filename = 'rekap-rekening-bulan-' . $bulan . '-' . $namaSekolah . '.xlsx';

        \App\Jobs\GenerateExportJob::dispatch(
            $exportJob->id,
            \App\Exports\RekapRekeningExport::class,
            ['bulan' => $bulan, 'tahunAnggaranId' => $tahunAnggaranAktif?->id, 'sumberDanaId' => $sumberDanaId, 'programId' => $request->input('program_id'), 'search' => $request->input('search')],
            $filename,
        );

        return redirect()->back()->with('info', 'Export Rekap Realisasi sedang diproses. <a href="' . route('exports.download', $exportJob->id) . '" class="font-semibold underline">Cek status</a>.');
    }

    public function rekapKuartalExportExcel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        $bulanVal = $request->input('bulan', date('n'));
        $bulan = is_numeric($bulanVal) ? (int) $bulanVal : (int) date('n');
        $kuartal = (int) ceil($bulan / 3);
        $profil = PengaturanSekolah::get();
        $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');

        $exportJob = ExportJob::create([
            'user_id' => auth()->id(),
            'type' => 'Rekap Tribulan Q' . $kuartal,
            'status' => 'processing',
        ]);

        $filename = 'rekap-kuartal-q' . $kuartal . '-' . $namaSekolah . '.xlsx';

        \App\Jobs\GenerateExportJob::dispatch(
            $exportJob->id,
            \App\Exports\RekapKuartalExport::class,
            ['kuartal' => $kuartal, 'namaSekolah' => $namaSekolah, 'tahunAnggaranId' => $tahunAnggaranAktif?->id, 'sumberDanaId' => $sumberDanaId, 'programId' => $request->input('program_id'), 'search' => $request->input('search')],
            $filename,
        );

        return redirect()->back()->with('info', 'Export Rekap Tribulan sedang diproses. <a href="' . route('exports.download', $exportJob->id) . '" class="font-semibold underline">Cek status</a>.');
    }

    public function rekapSiplahExportExcel(Request $request): \Illuminate\Http\RedirectResponse
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        $resolved = $this->resolveSiplahPeriode($request);
        $profil = PengaturanSekolah::get();
        $namaSekolah = $profil ? preg_replace('/[^a-zA-Z0-9]/', '_', $profil->nama) : 'sekolah';
        $periodeLabel = $resolved['label'];
        $slug = str_replace([' ', '–'], ['_', '-'], $periodeLabel);
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');

        $exportJob = ExportJob::create([
            'user_id' => auth()->id(),
            'type' => 'Rekap SIPLAH',
            'status' => 'processing',
        ]);

        $filename = 'rekap-siplah-' . $slug . '-' . $namaSekolah . '.xlsx';

        \App\Jobs\GenerateExportJob::dispatch(
            $exportJob->id,
            \App\Exports\RekapSiplahExport::class,
            ['months' => $resolved['months'], 'periodeLabel' => $resolved['label'], 'tahunAnggaranId' => $tahunAnggaranAktif?->id, 'sumberDanaId' => $sumberDanaId],
            $filename,
        );

        return redirect()->back()->with('info', 'Export Rekap SIPLAH sedang diproses. <a href="' . route('exports.download', $exportJob->id) . '" class="font-semibold underline">Cek status</a>.');
    }

    /**
     * @return array{months: int[], label: string}
     */
    private function resolveSiplahPeriode(Request $request): array
    {
        $periodeRaw = $request->input('periode', '');
        $periode = is_string($periodeRaw) ? $periodeRaw : '';
        $bulanRaw = $request->input('bulan', 0);
        $bulanParam = is_numeric($bulanRaw) ? (int) $bulanRaw : 0;

        if ($periode === 'h1') {
            return ['months' => [1, 2, 3, 4, 5, 6], 'label' => 'Januari – Juni'];
        } elseif ($periode === 'h2') {
            return ['months' => [7, 8, 9, 10, 11, 12], 'label' => 'Juli – Desember'];
        } elseif ($periode === 'all') {
            return ['months' => range(1, 12), 'label' => 'Seluruh Tahun'];
        } elseif ($bulanParam >= 1 && $bulanParam <= 12) {
            $label = \Carbon\Carbon::now()->month($bulanParam)->translatedFormat('F');
            return ['months' => [$bulanParam], 'label' => $label];
        }
        $currentMonth = (int) date('n');
        return ['months' => [$currentMonth], 'label' => \Carbon\Carbon::now()->month($currentMonth)->translatedFormat('F')];
    }

    private function resolveTahunAnggaran(Request $request): ?TahunAnggaran
    {
        $tahunAnggaranAktif = TahunAnggaran::getActive();
        $tahunInput = $request->input('tahun');
        if ($tahunInput) {
            $tahunRecord = TahunAnggaran::where('tahun', $tahunInput)->first();
            if ($tahunRecord) {
                return $tahunRecord;
            }
        }

        return $tahunAnggaranAktif;
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareRekapSiplahData(Request $request): array
    {
        $resolved = $this->resolveSiplahPeriode($request);
        $months = $resolved['months'];
        $periodeLabel = $resolved['label'];

        $bulan = $months[0];
        $rawTanggal = $request->get('tanggal_cetak', '');
        $tanggalCetak = $rawTanggal && \Carbon\Carbon::hasFormat($rawTanggal, 'Y-m-d')
            ? \Carbon\Carbon::parse($rawTanggal)->translatedFormat('d F Y')
            : ($rawTanggal ?: \Carbon\Carbon::now()->translatedFormat('d F Y'));
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();
        $sumberDanaList = SumberDana::orderBy('kode')->get();
        $sumberDanaId = $request->input('sumber_dana_id');
        $profil = PengaturanSekolah::get();

        $totalsQuery = RealisasiQuery::base()
            ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rb.rkas_item_id')
            ->whereIn('rb.bulan', $months)
            ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId));

        if ($tahunAnggaranAktif) {
            $totalsQuery->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id);
        }

        $totals = (clone $totalsQuery)->selectRaw("
            COALESCE(SUM(rb.jumlah), 0) as total,
            COALESCE(SUM(CASE WHEN rb.metode_pengadaan = 'siplah' THEN rb.jumlah ELSE 0 END), 0) as siplah,
            COALESCE(SUM(CASE WHEN rb.metode_pengadaan = 'non_siplah' THEN rb.jumlah ELSE 0 END), 0) as non_siplah
        ")->first();

        $totalPengeluaran = (float) ($totals->total ?? 0);
        $totalSiplah = (float) ($totals->siplah ?? 0);
        $totalNonSiplah = (float) ($totals->non_siplah ?? 0);
        $totalBelumDiisi = $totalPengeluaran - $totalSiplah - $totalNonSiplah;
        $persenSiplah = $totalPengeluaran > 0 ? round(($totalSiplah / $totalPengeluaran) * 100, 1) : 0;
        $persenNonSiplah = $totalPengeluaran > 0 ? round(($totalNonSiplah / $totalPengeluaran) * 100, 1) : 0;

        $breakdownRows = RealisasiQuery::base()
            ->leftJoin('rkas_item as ri_sub', 'ri_sub.id', '=', 'rb.rkas_item_id')
            ->leftJoin('master_kode_rekening as mkr_sub', 'mkr_sub.id', '=', 'ri_sub.kode_rekening_id')
            ->leftJoin('jenis_belanja as jb_sub', 'jb_sub.id', '=', 'mkr_sub.jenis_belanja_id')
            ->whereIn('rb.bulan', $months)
            ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId));

        if ($tahunAnggaranAktif) {
            $breakdownRows->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id);
        }

        $breakdownRows = $breakdownRows
            ->selectRaw("
                COALESCE(jb_sub.nama, 'Tidak Terkategori') as jenis_belanja,
                COALESCE(SUM(rb.jumlah), 0) as total,
                COALESCE(SUM(CASE WHEN rb.metode_pengadaan = 'siplah' THEN rb.jumlah ELSE 0 END), 0) as siplah,
                COALESCE(SUM(CASE WHEN rb.metode_pengadaan = 'non_siplah' THEN rb.jumlah ELSE 0 END), 0) as non_siplah
            ")
            ->groupBy('jb_sub.nama')
            ->orderBy('jb_sub.nama')
            ->get();

        $breakdown = $breakdownRows->map(function ($row) {
            $total = is_numeric($row->total) ? (float) $row->total : 0.0;
            $siplah = is_numeric($row->siplah) ? (float) $row->siplah : 0.0;
            $non_siplah = is_numeric($row->non_siplah) ? (float) $row->non_siplah : 0.0;
            return (object) [
                'jenis_belanja' => $row->jenis_belanja,
                'total' => $total,
                'siplah' => $siplah,
                'non_siplah' => $non_siplah,
                'persen_siplah' => $total > 0 ? round(($siplah / $total) * 100, 1) : 0,
                'persen_non_siplah' => $total > 0 ? round(($non_siplah / $total) * 100, 1) : 0,
            ];
        });

        return compact(
            'bulan', 'profil', 'tahunAnggaranAktif', 'tanggalCetak',
            'totalPengeluaran', 'totalSiplah', 'totalNonSiplah', 'totalBelumDiisi',
            'persenSiplah', 'persenNonSiplah', 'breakdown', 'periodeLabel', 'months',
            'tahunList', 'sumberDanaList', 'sumberDanaId'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareBkuData(Request $request): array
    {
        $bulan = (int) $request->get('bulan', date('n'));
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();
        $sumberDanaList = SumberDana::orderBy('kode')->get();
        $sumberDanaId = $request->input('sumber_dana_id');
        $profil = PengaturanSekolah::get();

        $transaksis = TransaksiBku::with(
            'rkasItem.program',
            'rkasItem.kodeRekening.jenisBelanja',
            'notaBku.kegiatan',
            'notaBku.kodeRekening.jenisBelanja'
        )
            ->where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $saldoAwal = (float) TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', '<', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'pengeluaran' THEN -jumlah WHEN LOWER(jenis) = 'penerimaan' AND COALESCE(kategori_arus,'') <> 'mutasi' THEN jumlah ELSE 0 END), 0) as saldo")
            ->value('saldo');

        $saldo = $saldoAwal;
        foreach ($transaksis as $t) {
            if (strtolower($t->jenis) === 'penerimaan' && ($t->kategori_arus ?? '') === 'mutasi') {
                // mutasi netral — tidak mengubah saldo berjalan
            } else {
                $saldo += strtolower($t->jenis) === 'penerimaan' ? $t->jumlah : -$t->jumlah;
            }
            $t->saldo_berjalan = $saldo;
        }

        $totals = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'penerimaan' AND COALESCE(kategori_arus,'') <> 'mutasi' THEN jumlah ELSE 0 END), 0) as total_penerimaan")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as total_pengeluaran")
            ->firstOrFail();
        $totalPenerimaan = (float) $totals->getAttribute('total_penerimaan');
        $totalPengeluaran = (float) $totals->getAttribute('total_pengeluaran');
        $saldoAkhir = $saldoAwal + $totalPenerimaan - $totalPengeluaran;

        return compact('transaksis', 'profil', 'bulan', 'tahunAnggaranAktif',
            'saldoAwal', 'totalPenerimaan', 'totalPengeluaran', 'saldoAkhir', 'tahunList',
            'sumberDanaList', 'sumberDanaId');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\RkasItemBulan>|null $rencanaSub
     * @param \Illuminate\Database\Query\Builder|null $realisasiSub
     * @return \Illuminate\Support\Collection<int, \App\Models\RkasItem>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\RkasItem>
     */
    private function loadRekapRekeningItems(?TahunAnggaran $tahunAnggaranAktif, int $bulan, ?int $perPage = null, $rencanaSub = null, $realisasiSub = null): \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $sumberDanaId = request('sumber_dana_id');
        $programId = request('program_id');

        if ($rencanaSub === null) {
            $rencanaSub = RkasItemBulan::selectRaw('rkas_item_bulan.rkas_item_id, SUM(rkas_item_bulan.rencana) as total')
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rkas_item_bulan.rkas_item_id')
                ->where('rkas_item_bulan.bulan', $bulan)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('rkas_item_bulan.rkas_item_id');
        }

        if ($realisasiSub === null) {
            $realisasiSub = RealisasiQuery::base()
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rb.rkas_item_id')
                ->selectRaw('rb.rkas_item_id, SUM(rb.jumlah) as total')
                ->where('rb.bulan', $bulan)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('rb.rkas_item_id');
        }

        $query = RkasItem::with('kodeRekening.jenisBelanja', 'program')
            ->select('rkas_item.*')
            ->selectRaw('COALESCE(rib.total, 0) as rencana_bulan')
            ->selectRaw('COALESCE(tb.total, 0) as realisasi_bulan');
        $query->leftJoinSub($rencanaSub, 'rib', fn($j) => $j->on('rkas_item.id', '=', 'rib.rkas_item_id'));
        $query->leftJoinSub($realisasiSub, 'tb', fn($j) => $j->on('rkas_item.id', '=', 'tb.rkas_item_id'));
        $query->where('rkas_item.tahun_anggaran_id', $tahunAnggaranAktif->id);

        $search = request('search');
        if ($search) {
            $query->where('rkas_item.uraian', 'like', "%{$search}%");
        }

        if ($sumberDanaId) {
            $query->where('rkas_item.sumber_dana_id', $sumberDanaId);
        }

        $mapFn = function (RkasItem $item) {
            $rencana = (float) $item->getAttribute('rencana_bulan');
            $realisasi = (float) $item->getAttribute('realisasi_bulan');
            $item->sisa_bulan = $rencana - $realisasi;
            $item->persen = $rencana > 0 ? round(($realisasi / $rencana) * 100, 1) : 0;
            return $item;
        };

        if ($perPage) {
            return $query->paginate($perPage)->withQueryString()->through($mapFn);
        }

        return $query->get()->map($mapFn);
    }

    /**
     * @param int[] $bulanMonths
     * @param \Illuminate\Database\Query\Builder|null $realisasiSub
     * @return \Illuminate\Support\Collection<int, \App\Models\RkasItem>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\RkasItem>
     */
    private function loadKuartalItems(?TahunAnggaran $tahunAnggaranAktif, array $bulanMonths, ?int $perPage = null, $realisasiSub = null): \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $cases = [];
        foreach ($bulanMonths as $i => $b) {
            $cases[] = "SUM(CASE WHEN rb.bulan = {$b} THEN rb.jumlah ELSE 0 END) as m{$i}";
        }
        $casesSql = implode(', ', $cases);

        $sumberDanaId = request('sumber_dana_id');
        $programId = request('program_id');

        if ($realisasiSub === null) {
            $realisasiSub = RealisasiQuery::base()
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rb.rkas_item_id')
                ->selectRaw("rb.rkas_item_id, {$casesSql}, SUM(rb.jumlah) as total_all")
                ->whereIn('rb.bulan', $bulanMonths)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('rb.rkas_item_id');
        }

        $query = RkasItem::with('kodeRekening.jenisBelanja', 'program')
            ->select('rkas_item.*')
            ->selectRaw('COALESCE(tb.m0, 0) as m0, COALESCE(tb.m1, 0) as m1, COALESCE(tb.m2, 0) as m2, COALESCE(tb.total_all, 0) as total_all')
            ->leftJoinSub($realisasiSub, 'tb', fn($j) => $j->on('rkas_item.id', '=', 'tb.rkas_item_id'))
            ->where('rkas_item.tahun_anggaran_id', $tahunAnggaranAktif->id);

        $search = request('search');
        if ($search) {
            $query->where('rkas_item.uraian', 'like', "%{$search}%");
        }

        if ($sumberDanaId) {
            $query->where('rkas_item.sumber_dana_id', $sumberDanaId);
        }

        $mapFn = function (RkasItem $item) use ($bulanMonths) {
            $realisasiPerBulan = [];
            $totalRealisasi = 0;
            foreach ($bulanMonths as $i => $b) {
                $r = floatval($item->getAttribute("m{$i}") ?? 0);
                $realisasiPerBulan[$b] = $r;
                $totalRealisasi += $r;
            }
            $item->realisasi_per_bulan = $realisasiPerBulan;
            $item->total_realisasi = $totalRealisasi;
            return $item;
        };

        if ($perPage) {
            return $query->paginate($perPage)->withQueryString()->through($mapFn);
        }

        return $query->get()->map($mapFn);
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareRekapRekeningData(Request $request, ?int $perPage = null): array
    {
        $bulan = (int) $request->get('bulan', date('n'));
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();
        $sumberDanaList = SumberDana::orderBy('kode')->get();
        $sumberDanaId = $request->input('sumber_dana_id');
        $programId = $request->input('program_id');
        $profil = PengaturanSekolah::get();
        $programs = Cache::remember('master_programs', 86400, fn() => MasterProgram::all());
        $search = $request->get('search');

        $rencanaSub = $tahunAnggaranAktif
            ? RkasItemBulan::selectRaw('rkas_item_bulan.rkas_item_id, SUM(rkas_item_bulan.rencana) as total')
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rkas_item_bulan.rkas_item_id')
                ->where('rkas_item_bulan.bulan', $bulan)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('rkas_item_bulan.rkas_item_id')
            : null;

        $realisasiSub = $tahunAnggaranAktif
            ? RealisasiQuery::base()
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rb.rkas_item_id')
                ->selectRaw('rb.rkas_item_id, SUM(rb.jumlah) as total')
                ->where('rb.bulan', $bulan)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('rb.rkas_item_id')
            : null;

        $rkasItems = $tahunAnggaranAktif
            ? $this->loadRekapRekeningItems($tahunAnggaranAktif, $bulan, $perPage, $rencanaSub, $realisasiSub)
            : ($perPage ? new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage) : collect());

        $subtotals = collect();
        $grandTotalRencana = 0;
        $grandTotalRealisasi = 0;

        if ($tahunAnggaranAktif && $perPage) {
            $rows = RkasItem::withTrashed()->from('rkas_item as ri')
                ->join('master_kode_rekening as mkr', 'mkr.id', '=', 'ri.kode_rekening_id')
                ->join('jenis_belanja as jb', 'jb.id', '=', 'mkr.jenis_belanja_id')
                ->selectRaw('jb.nama, COALESCE(SUM(rib.total), 0) as total_rencana, COALESCE(SUM(tb.total), 0) as total_realisasi')
                ->leftJoinSub($rencanaSub, 'rib', fn($j) => $j->on('ri.id', '=', 'rib.rkas_item_id'));
            $rows->leftJoinSub($realisasiSub, 'tb', fn($j) => $j->on('ri.id', '=', 'tb.rkas_item_id'));
            $rows = $rows->whereNull('ri.deleted_at')
                ->where('ri.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($search, fn($q) => $q->where('ri.uraian', 'like', "%{$search}%"))
                ->when($sumberDanaId, fn($q) => $q->where('ri.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri.program_id', $programId))
                ->groupBy('jb.nama')
                ->orderBy('jb.nama')
                ->get();

            foreach ($rows as $row) {
                $ren = (float) $row->total_rencana;
                $rea = (float) $row->total_realisasi;
                $subtotals[$row->nama] = [
                    'rencana' => $ren,
                    'realisasi' => $rea,
                    'sisa' => $ren - $rea,
                    'persen' => $ren > 0 ? round(($rea / $ren) * 100, 1) : 0,
                ];
                $grandTotalRencana += $ren;
                $grandTotalRealisasi += $rea;
            }
        }

        $grandTotalSisa = $grandTotalRencana - $grandTotalRealisasi;
        $grandTotalPersen = $grandTotalRencana > 0 ? round(($grandTotalRealisasi / $grandTotalRencana) * 100, 1) : 0;

        if ($perPage) {
            return compact('rkasItems', 'profil', 'bulan', 'tahunAnggaranAktif',
                'subtotals', 'grandTotalRencana', 'grandTotalRealisasi', 'grandTotalSisa', 'grandTotalPersen', 'tahunList',
                'sumberDanaList', 'sumberDanaId', 'programs', 'programId');
        }

        $grouped = $rkasItems instanceof \Illuminate\Support\Collection
            ? $rkasItems->groupBy(fn(RkasItem $item): string => $item->kodeRekening->jenisBelanja->nama ?? 'Tidak Terkategori')
            : collect();
        return compact('grouped', 'rkasItems', 'profil', 'bulan', 'tahunAnggaranAktif', 'tahunList', 'sumberDanaList', 'sumberDanaId', 'programs', 'programId');
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareRekapKuartalData(Request $request, ?int $perPage = null): array
    {
        $bulan = (int) $request->get('bulan', date('n'));
        $kuartal = (int) ceil($bulan / 3);
        $startMonth = ($kuartal - 1) * 3 + 1;
        $bulanMonths = [$startMonth, $startMonth + 1, $startMonth + 2];
        $bulanNames = array_map(
            fn($m) => \Carbon\Carbon::createFromDate(null, $m, 1)->translatedFormat('F'),
            $bulanMonths
        );
        $qLabel = 'Q' . $kuartal;
        $periodeLabel = implode(' s.d. ', $bulanNames);

        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();
        $sumberDanaList = SumberDana::orderBy('kode')->get();
        $sumberDanaId = $request->input('sumber_dana_id');
        $programId = $request->input('program_id');
        $profil = PengaturanSekolah::get();
        $programs = Cache::remember('master_programs', 86400, fn() => MasterProgram::all());

        $cases = [];
        foreach ($bulanMonths as $i => $b) {
            $cases[] = "SUM(CASE WHEN rb.bulan = {$b} THEN rb.jumlah ELSE 0 END) as m{$i}";
        }
        $casesSql = implode(', ', $cases);

        $realisasiSub = $tahunAnggaranAktif
            ? RealisasiQuery::base()
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rb.rkas_item_id')
                ->selectRaw("rb.rkas_item_id, {$casesSql}, SUM(rb.jumlah) as total_all")
                ->whereIn('rb.bulan', $bulanMonths)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('rb.rkas_item_id')
            : null;

        $quarterlyItems = $tahunAnggaranAktif && $realisasiSub
            ? $this->loadKuartalItems($tahunAnggaranAktif, $bulanMonths, $perPage, $realisasiSub)
            : ($perPage ? new \Illuminate\Pagination\LengthAwarePaginator([], 0, $perPage) : collect());

        $subtotals = collect();
        $grandTotalPerBulan = array_fill_keys($bulanMonths, 0);
        $grandTotalAll = 0;

        if ($tahunAnggaranAktif && $perPage) {
            $search = $request->get('search');

            $rows = RkasItem::withTrashed()->from('rkas_item as ri')
                ->join('master_kode_rekening as mkr', 'mkr.id', '=', 'ri.kode_rekening_id')
                ->join('jenis_belanja as jb', 'jb.id', '=', 'mkr.jenis_belanja_id')
                ->leftJoinSub($realisasiSub, 'tb', fn($j) => $j->on('ri.id', '=', 'tb.rkas_item_id'));
            $rows = $rows->whereNull('ri.deleted_at')
                ->where('ri.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($search, fn($q) => $q->where('ri.uraian', 'like', "%{$search}%"))
                ->when($sumberDanaId, fn($q) => $q->where('ri.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri.program_id', $programId))
                ->selectRaw('jb.nama')
                ->selectRaw("
                    COALESCE(SUM(tb.m0), 0) as m0,
                    COALESCE(SUM(tb.m1), 0) as m1,
                    COALESCE(SUM(tb.m2), 0) as m2,
                    COALESCE(SUM(tb.total_all), 0) as total
                ")
                ->groupBy('jb.nama')
                ->orderBy('jb.nama')
                ->get();

            foreach ($rows as $row) {
                $perBulan = [floatval($row->m0), floatval($row->m1), floatval($row->m2)];
                $total = floatval($row->total);
                $subtotals[$row->nama] = [
                    'per_bulan' => array_combine($bulanMonths, $perBulan),
                    'total' => $total,
                ];
                foreach ($bulanMonths as $i => $b) {
                    $grandTotalPerBulan[$b] += $perBulan[$i];
                }
                $grandTotalAll += $total;
            }
        }

        if ($perPage) {
            return compact('quarterlyItems', 'profil', 'tahunAnggaranAktif',
                'qLabel', 'periodeLabel', 'bulanMonths', 'bulanNames', 'kuartal', 'bulan',
                'subtotals', 'grandTotalPerBulan', 'grandTotalAll', 'tahunList',
                'sumberDanaList', 'sumberDanaId', 'programs', 'programId');
        }

        $grouped = $quarterlyItems instanceof \Illuminate\Support\Collection
            ? $quarterlyItems->groupBy(fn(RkasItem $item): string => $item->kodeRekening->jenisBelanja->nama ?? 'Tidak Terkategori')
            : collect();
        return compact('grouped', 'profil', 'tahunAnggaranAktif',
            'qLabel', 'periodeLabel', 'bulanMonths', 'bulanNames', 'kuartal', 'bulan', 'tahunList',
            'sumberDanaList', 'sumberDanaId', 'programs', 'programId');
    }

    public function k7b(Request $request): \Illuminate\Http\Response|\Illuminate\View\View
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        if ($request->get('cetak') == 'pdf') {
            set_time_limit(0);
            ini_set('memory_limit', -1);
        }

        $data = $this->prepareK7Data($request);

        if ($request->get('cetak') == 'pdf') {
            $namaSekolah = $data['profil'] ? preg_replace('/[^a-zA-Z0-9]/', '_', (string) $data['profil']->nama) : 'sekolah';
            $bulanLabel = $data['bulan'] ? Carbon::createFromDate(null, (int) $data['bulan'], 1)->translatedFormat('F') : '';
            $tahunLabel = (string) $data['tahun'];
            $pdf = Pdf::loadView('laporan.k7b-pdf', $data)->setPaper('a4', 'portrait');

            return $this->streamPdf($pdf, 'Formulir_BOS_K7b-' . $namaSekolah . '-' . $bulanLabel . '_' . $tahunLabel . '.pdf');
        }

        return view('laporan.k7b', $data);
    }

    public function k7c(Request $request): \Illuminate\Http\Response|\Illuminate\View\View
    {
        $authUser = auth()->user();
        if ($authUser === null) {
            abort(403);
        }
        if ($request->get('cetak') == 'pdf') {
            set_time_limit(0);
            ini_set('memory_limit', -1);
        }

        $data = $this->prepareK7Data($request);

        if ($request->get('cetak') == 'pdf') {
            $namaSekolah = $data['profil'] ? preg_replace('/[^a-zA-Z0-9]/', '_', (string) $data['profil']->nama) : 'sekolah';
            $bulanLabel = $data['bulan'] ? Carbon::createFromDate(null, (int) $data['bulan'], 1)->translatedFormat('F') : '';
            $tahunLabel = (string) $data['tahun'];
            $pdf = Pdf::loadView('laporan.k7c-pdf', $data)->setPaper('a4', 'portrait');

            return $this->streamPdf($pdf, 'Formulir_BOS_K7c-' . $namaSekolah . '-' . $bulanLabel . '_' . $tahunLabel . '.pdf');
        }

        return view('laporan.k7c', $data);
    }

    /**
     * Simpan hasil opname penutupan kas bulan tertentu (Formulir BOS-K7b).
     * Satu baris per (tahun_anggaran, bulan, sumber_dana) — upsert manual karena
     * sumber_dana_id bisa NULL (NULL tidak cocok dengan '=' di SQL).
     */
    public function simpanK7b(Request $request): \Illuminate\Http\RedirectResponse
    {
        $tahunAnggaranAktif = TahunAnggaran::getActive();
        if ($tahunAnggaranAktif === null) {
            return back()->with('error', 'Tidak ada tahun anggaran aktif.');
        }

        $rules = [];
        foreach (array_keys(KasPenutupan::daftarKertas()) as $key) {
            $rules['kertas_' . $key] = ['required', 'integer', 'min:0'];
        }
        foreach (array_keys(KasPenutupan::daftarLogam()) as $key) {
            $rules['logam_' . $key] = ['required', 'integer', 'min:0'];
        }
        $rules['saldo_bank'] = ['required'];
        $rules['tanggal_penutupan'] = ['nullable', 'date'];
        $rules['catatan'] = ['nullable', 'string', 'max:500'];

        $validated = $request->validate($rules);

        $bulan = max(1, min(12, (int) $request->input('bulan', now()->month)));
        $sumberDanaId = $request->input('sumber_dana_id');

        $payload = [
            'tanggal_penutupan' => $validated['tanggal_penutupan'] ?? null,
            'saldo_bank' => (float) NumberParser::rupiah((string) $validated['saldo_bank']),
            'catatan' => $validated['catatan'] ?? null,
            'created_by' => auth()->id(),
        ];
        foreach (KasPenutupan::daftarKertas() as $key => $nominal) {
            $payload[$key] = max(0, (int) $validated['kertas_' . $key]);
        }
        foreach (KasPenutupan::daftarLogam() as $key => $nominal) {
            $payload[$key] = max(0, (int) $validated['logam_' . $key]);
        }

        // Lookup manual (bukan updateOrCreate) — NULL sumber_dana_id tidak cocok dengan '='.
        $penutupan = KasPenutupan::where('tahun_anggaran_id', $tahunAnggaranAktif->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId), fn($q) => $q->whereNull('sumber_dana_id'))
            ->first();

        if ($penutupan === null) {
            $penutupan = new KasPenutupan;
            $penutupan->tahun_anggaran_id = $tahunAnggaranAktif->id;
            $penutupan->bulan = $bulan;
            $penutupan->sumber_dana_id = $sumberDanaId;
            $aksi = 'create';
        } else {
            $aksi = 'update';
        }
        $penutupan->fill($payload);
        $penutupan->save();

        AuditLog::record('kas_penutupan', $aksi, [
            'bulan' => $bulan,
            'sumber_dana_id' => $sumberDanaId,
            'subtotal_fisik' => $penutupan->subtotalFisik(),
            'saldo_bank' => (float) $penutupan->saldo_bank,
            'total_riil' => $penutupan->totalRiil(),
        ]);

        $namaBulan = Carbon::createFromDate((int) now()->year, $bulan, 1)->translatedFormat('F');

        return redirect()
            ->route('laporan.k7b', array_filter([
                'bulan' => $bulan,
                'tahun_anggaran_id' => $tahunAnggaranAktif->id,
                'sumber_dana_id' => $sumberDanaId,
            ]))
            ->with('success', 'Data penutupan kas bulan ' . $namaBulan . ' berhasil disimpan.');
    }

    /**
     * Register Penutupan Kas multi-bulan (Formulir BOS-K7b lanjutan):
     * rekap A/D/K/Sisa per bulan + hasil opname fisik & bank yang tersimpan.
     * Output PDF landscape.
     */
    public function registerK7b(Request $request): mixed
    {
        $tahunParam = $request->input('tahun_anggaran_id');
        $tahunAnggaran = $tahunParam !== null && $tahunParam !== ''
            ? TahunAnggaran::find($tahunParam)
            : TahunAnggaran::getActive();

        if ($tahunAnggaran === null) {
            return redirect()->route('laporan.index')->with('error', 'Tidak ada tahun anggaran aktif.');
        }

        $dari = max(1, min(12, (int) $request->input('dari', 1)));
        $sampai = max(1, min(12, (int) $request->input('sampai', 12)));
        if ($sampai < $dari) {
            [$dari, $sampai] = [$sampai, $dari];
        }
        $sumberDanaId = $request->input('sumber_dana_id');

        $scope = fn($q) => $q
            ->where('tahun_anggaran_id', $tahunAnggaran->id)
            ->when($sumberDanaId, fn($q2) => $q2->where('sumber_dana_id', $sumberDanaId));

        // Saldo awal periode register: kumulatif s.d. sebelum bulan "dari" (non-mutasi)
        // + total pencairan SP2D s.d. bulan yang sama (uang masuk rekening bank).
        $saldoAwalRecord = TransaksiBku::query()
            ->tap(fn($q) => $scope($q))
            ->where('bulan', '<', $dari)
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'pengeluaran' THEN -jumlah WHEN LOWER(jenis) = 'penerimaan' AND COALESCE(kategori_arus, '') <> 'mutasi' THEN jumlah ELSE 0 END), 0) as saldo")
            ->first();
        $pencairanAwal = (float) Pencairan::where('tahun_anggaran_id', $tahunAnggaran->id)
            ->when($sumberDanaId, fn($q2) => $q2->where('sumber_dana_id', $sumberDanaId))
            ->where('bulan', '<', $dari)
            ->sum('nominal');
        $runningSaldo = (float) ($saldoAwalRecord?->getAttribute('saldo') ?? 0) + $pencairanAwal;

        $rows = [];
        for ($m = $dari; $m <= $sampai; $m++) {
            $totals = TransaksiBku::query()
                ->tap(fn($q) => $scope($q))
                ->where('bulan', $m)
                ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'penerimaan' AND COALESCE(kategori_arus, '') <> 'mutasi' THEN jumlah ELSE 0 END), 0) as total_penerimaan")
                ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as total_pengeluaran")
                ->first();

            $penerimaanBku = (float) ($totals?->getAttribute('total_penerimaan') ?? 0);
            $pencairanBulan = (float) Pencairan::where('tahun_anggaran_id', $tahunAnggaran->id)
                ->when($sumberDanaId, fn($q2) => $q2->where('sumber_dana_id', $sumberDanaId))
                ->where('bulan', $m)
                ->sum('nominal');
            $penerimaan = $penerimaanBku + $pencairanBulan;
            $pengeluaran = (float) ($totals?->getAttribute('total_pengeluaran') ?? 0);
            $sisa = $runningSaldo + $penerimaan - $pengeluaran;

            // Lookup manual (NULL-safe sumber_dana_id).
            $penutupan = KasPenutupan::where('tahun_anggaran_id', $tahunAnggaran->id)
                ->where('bulan', $m)
                ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId), fn($q) => $q->whereNull('sumber_dana_id'))
                ->first();

            $adaOpname = $penutupan !== null;

            $rows[] = [
                'bulan' => $m,
                'label' => Carbon::createFromDate((int) $tahunAnggaran->tahun, $m, 1)->translatedFormat('F Y'),
                'tanggal' => $penutupan?->tanggal_penutupan,
                'awal' => $runningSaldo,
                'penerimaan' => $penerimaan,
                'pengeluaran' => $pengeluaran,
                'sisa' => $sisa,
                'fisik' => $adaOpname ? $penutupan->subtotalFisik() : null,
                'bank' => $adaOpname ? (float) $penutupan->saldo_bank : null,
                'riil' => $adaOpname ? $penutupan->totalRiil() : null,
                'perbedaan' => $adaOpname ? round($sisa - $penutupan->totalRiil(), 2) : null,
            ];

            $runningSaldo = $sisa;
        }

        $sumberDana = filled($sumberDanaId) ? SumberDana::find($sumberDanaId) : null;

        $data = [
            'profil' => PengaturanSekolah::get(),
            'tahunAnggaran' => $tahunAnggaran,
            'sumberDanaLabel' => $sumberDana !== null ? $sumberDana->nama : 'Semua Sumber Dana',
            'dari' => $dari,
            'sampai' => $sampai,
            'rows' => collect($rows),
        ];

        $pdf = Pdf::loadView('laporan.k7b-register-pdf', $data)->setPaper('a4', 'landscape');

        return $this->streamPdf($pdf, sprintf(
            'Register_Penutupan_Kas_K7b-%s-%d.pdf',
            str_replace(' ', '_', (string) ($data['profil']->nama ?? 'Sekolah')),
            $tahunAnggaran->tahun
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareK7Data(Request $request): array
    {
        $bulanVal = $request->input('bulan', date('n'));
        $bulan = is_numeric($bulanVal) ? (int) $bulanVal : (int) date('n');
        $tahunAnggaranAktif = $this->resolveTahunAnggaran($request);
        $sumberDanaId = $request->input('sumber_dana_id');
        $profil = PengaturanSekolah::get();
        $tahun = $tahunAnggaranAktif !== null ? (int) $tahunAnggaranAktif->tahun : (int) date('Y');

        // Data penutupan kas tersimpan untuk periode terpilih (fallback nilai form).
        // Lookup manual (bukan updateOrCreate-style firstOrNew) karena sumber_dana_id
        // bisa NULL dan NULL tidak cocok dengan '=' di SQL.
        $kasPenutupan = KasPenutupan::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId), fn($q) => $q->whereNull('sumber_dana_id'))
            ->first();

        // Tanggal Penutupan Kas Bulan ini & Bulan lalu
        $lastDayOfMonth = Carbon::create($tahun, $bulan, 1)->endOfMonth();
        $prevMonthLastDay = Carbon::create($tahun, $bulan, 1)->subMonth()->endOfMonth();

        // Guard sticky-date: field tanggal ikut ter-submit ulang saat user mengganti
        // Bulan/Tahun lewat filter GET. Bila bulan/tahun tanggal yang dikirim tidak
        // cocok dengan periode terpilih, abaikan dan pakai akhir bulan terpilih.
        $rawTanggalPenutupan = $request->input('tanggal_penutupan');
        if (is_string($rawTanggalPenutupan) && Carbon::hasFormat($rawTanggalPenutupan, 'Y-m-d')) {
            $parsedTanggal = Carbon::parse($rawTanggalPenutupan);
            if ((int) $parsedTanggal->month !== $bulan || (int) $parsedTanggal->year !== $tahun) {
                $rawTanggalPenutupan = null;
            }
        } else {
            $rawTanggalPenutupan = null;
        }

        $rawTanggalPenutupanLalu = $request->input('tanggal_penutupan_lalu');
        if (is_string($rawTanggalPenutupanLalu) && Carbon::hasFormat($rawTanggalPenutupanLalu, 'Y-m-d')) {
            $parsedTanggalLalu = Carbon::parse($rawTanggalPenutupanLalu);
            if ((int) $parsedTanggalLalu->month !== (int) $prevMonthLastDay->month || (int) $parsedTanggalLalu->year !== (int) $prevMonthLastDay->year) {
                $rawTanggalPenutupanLalu = null;
            }
        } else {
            $rawTanggalPenutupanLalu = null;
        }

        $tanggalPenutupanCarbon = $rawTanggalPenutupan !== null
            ? Carbon::parse($rawTanggalPenutupan)
            : ($kasPenutupan !== null && filled($kasPenutupan->tanggal_penutupan)
                ? Carbon::parse($kasPenutupan->tanggal_penutupan)
                : $lastDayOfMonth);
        $tanggalPenutupanLaluCarbon = $rawTanggalPenutupanLalu !== null
            ? Carbon::parse($rawTanggalPenutupanLalu)
            : $prevMonthLastDay;

        $tanggalPenutupan = $tanggalPenutupanCarbon->translatedFormat('d F Y');
        $tanggalPenutupanInput = $tanggalPenutupanCarbon->format('Y-m-d');

        $tanggalPenutupanLalu = $tanggalPenutupanLaluCarbon->translatedFormat('d F Y');
        $tanggalPenutupanLaluInput = $tanggalPenutupanLaluCarbon->format('Y-m-d');

        // Nama hari eksplisit via mapping EN→ID agar tidak tergantung APP_LOCALE
        // yang bisa berbeda antara web dan bundle desktop.
        $hariIndonesia = [
            'Sunday' => 'Minggu', 'Monday' => 'Senin', 'Tuesday' => 'Selasa',
            'Wednesday' => 'Rabu', 'Thursday' => 'Kamis', 'Friday' => 'Jumat',
            'Saturday' => 'Sabtu',
        ];
        $hariPenutupan = $hariIndonesia[$tanggalPenutupanCarbon->format('l')] ?? '';

        // BKU Calculations
        // Semantik resmi K7b: kas + bank digabung. Tarik tunai (kategori_arus =
        // 'mutasi') hanyalah perpindahan brankas -> rekening (atau sebaliknya),
        // jadi TIDAK dihitung sebagai penerimaan; saldo awal & D keduanya
        // mengecualikannya agar Sisa Saldo (A = Awal + D - K) mencakup bank.
        $saldoAwalRecord = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', '<', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'pengeluaran' THEN -jumlah WHEN LOWER(jenis) = 'penerimaan' AND COALESCE(kategori_arus, '') <> 'mutasi' THEN jumlah ELSE 0 END), 0) as saldo")
            ->first();
        $saldoAwal = $saldoAwalRecord ? (float) $saldoAwalRecord->getAttribute('saldo') : 0.0;

        $monthTotals = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'penerimaan' AND COALESCE(kategori_arus, '') <> 'mutasi' THEN jumlah ELSE 0 END), 0) as total_penerimaan")
            ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as total_pengeluaran")
            ->first();

        $pencairanScope = fn($q) => $q->where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->when($sumberDanaId, fn($q2) => $q2->where('sumber_dana_id', $sumberDanaId));

        // Pencairan SP2D (modul Data Pencairan) = uang masuk ke rekening BANK,
        // sehingga dihitung sebagai penerimaan pada Total Penerimaan (D).
        $pencairanSdBulanLalu = (float) Pencairan::query()->tap(fn($q) => $pencairanScope($q))->where('bulan', '<', $bulan)->sum('nominal');
        $pencairanBulanIni = (float) Pencairan::query()->tap(fn($q) => $pencairanScope($q))->where('bulan', $bulan)->sum('nominal');

        // Estimasi isi rekening bank s.d. bulan terpilih = total pencairan -
        // total tarik tunai (mutasi brankas). Dipakai sbg default kolom (3)
        // saat user belum mengisi & belum ada opname tersimpan.
        $tarikTunaiSdBulan = (float) TransaksiBku::query()
            ->tap(fn($q) => $q->where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
                ->when($sumberDanaId, fn($q2) => $q2->where('sumber_dana_id', $sumberDanaId)))
            ->where('jenis', 'penerimaan')
            ->where('kategori_arus', 'mutasi')
            ->where('bulan', '<=', $bulan)
            ->sum('jumlah');
        $totalPencairan = $pencairanSdBulanLalu + $pencairanBulanIni;
        $estimasiSaldoBank = max(0.0, $totalPencairan - $tarikTunaiSdBulan);

        $penerimaanBulanIni = ($monthTotals ? (float) $monthTotals->getAttribute('total_penerimaan') : 0.0) + $pencairanBulanIni;
        $totalPengeluaranK = $monthTotals ? (float) $monthTotals->getAttribute('total_pengeluaran') : 0.0;
        // Saldo awal periode = kas & bank s.d. bulan lalu (termasuk pencairan).
        $saldoAwal += $pencairanSdBulanLalu;
        $totalPenerimaanD = $saldoAwal + $penerimaanBulanIni;
        $saldoBkuA = $totalPenerimaanD - $totalPengeluaranK;

        // Rincian Pecahan Uang Kertas
        // Kunci = nama kolom persis (lembar_*/keping_*) agar name input form
        // (kertas_{{ $key }}) selalu cocok dengan rules validasi simpanK7b()
        // yang dibangun dari KasPenutupan::daftarKertas()/daftarLogam().
        $denominasiKertas = [
            'lembar_100000' => ['label' => '100.000', 'nominal' => 100000],
            'lembar_50000' => ['label' => '50.000', 'nominal' => 50000],
            'lembar_20000' => ['label' => '20.000', 'nominal' => 20000],
            'lembar_10000' => ['label' => '10.000', 'nominal' => 10000],
            'lembar_5000' => ['label' => '5.000', 'nominal' => 5000],
            'lembar_2000' => ['label' => '2.000', 'nominal' => 2000],
            'lembar_1000' => ['label' => '1.000', 'nominal' => 1000],
        ];

        $rincianKertas = [];
        $subtotalKertas = 0.0;
        foreach ($denominasiKertas as $key => $d) {
            // Prioritas: input form (live) > data tersimpan periode ini > 0.
            if ($request->has('kertas_' . $key)) {
                $lembar = max(0, (int) $request->input('kertas_' . $key, 0));
            } else {
                $lembar = $kasPenutupan !== null ? max(0, (int) $kasPenutupan->getAttribute($key)) : 0;
            }
            $total = $lembar * $d['nominal'];
            $subtotalKertas += $total;
            $rincianKertas[$key] = [
                'nominal' => $d['nominal'],
                'label' => $d['label'],
                'lembar' => $lembar,
                'total' => $total,
            ];
        }

        // Rincian Pecahan Uang Logam
        $denominasiLogam = [
            'keping_500' => ['label' => '500', 'nominal' => 500],
            'keping_200' => ['label' => '200', 'nominal' => 200],
            'keping_100' => ['label' => '100', 'nominal' => 100],
            'keping_50' => ['label' => '50', 'nominal' => 50],
        ];

        $rincianLogam = [];
        $subtotalLogam = 0.0;
        foreach ($denominasiLogam as $key => $d) {
            if ($request->has('logam_' . $key)) {
                $keping = max(0, (int) $request->input('logam_' . $key, 0));
            } else {
                $keping = $kasPenutupan !== null ? max(0, (int) $kasPenutupan->getAttribute($key)) : 0;
            }
            $total = $keping * $d['nominal'];
            $subtotalLogam += $total;
            $rincianLogam[$key] = [
                'nominal' => $d['nominal'],
                'label' => $d['label'],
                'keping' => $keping,
                'total' => $total,
            ];
        }

        // Subtotal Fisik Kas (1 + 2)
        $subtotalFisikKas = $subtotalKertas + $subtotalLogam;

        // Override langsung nilai fisik kas via query string (?kas_fisik=...)
        // dipakai halaman K-7c yang hanya punya satu input "Saldo Kas Tunai"
        // (tanpa input denominasi) agar hasil edit di layar ikut ke PDF.
        $rawKasFisik = $request->input('kas_fisik');
        if (is_string($rawKasFisik) && trim($rawKasFisik) !== '') {
            $cleanKas = str_replace(['.', ','], ['', '.'], trim($rawKasFisik));
            $subtotalFisikKas = max(0.0, (float) $cleanKas);
        }

        // Saldo Bank (3)
        $rawSaldoBank = $request->input('saldo_bank');
        if ($rawSaldoBank !== null && $rawSaldoBank !== '') {
            $cleanBank = is_string($rawSaldoBank) ? str_replace(['.', ','], ['', '.'], $rawSaldoBank) : $rawSaldoBank;
            $saldoBank = (float) $cleanBank;
        } elseif ($kasPenutupan !== null) {
            // Nilai tersimpan periode ini (hasil opname sebelumnya).
            $saldoBank = (float) $kasPenutupan->saldo_bank;
        } else {
            // Default bila belum ada opname tersimpan: bila lembaran kas sudah
            // diisi, sisanya diasumsikan berada di rekening; bila belum diisi,
            // pakai estimasi mutasi rekening (pencairan - tarik tunai).
            $saldoBank = $subtotalFisikKas > 0 ? max(0.0, $saldoBkuA - $subtotalFisikKas) : $estimasiSaldoBank;
        }

        // Total Kas (1 + 2 + 3 = B)
        $totalKasB = $subtotalFisikKas + $saldoBank;

        // Perbedaan (A - B)
        $perbedaan = $saldoBkuA - $totalKasB;
        $defaultPenjelasan = abs($perbedaan) < 0.01 ? 'NIHIL' : 'Selisih Rp ' . number_format(abs($perbedaan), 0, ',', '.');
        $rawPenjelasan = $request->input('penjelasan_perbedaan');
        $penjelasanPerbedaan = is_string($rawPenjelasan) && trim($rawPenjelasan) !== '' ? trim($rawPenjelasan) : $defaultPenjelasan;

        // SK Pengangkatan (untuk K-7c)
        $rawSkKepsek = $request->input('sk_bupati_kepsek');
        $skBupatiKepsek = is_string($rawSkKepsek) && trim($rawSkKepsek) !== '' ? trim($rawSkKepsek) : '821.2/421/424.103/2021';

        $rawSkBendahara = $request->input('sk_bupati_bendahara');
        $skBupatiBendahara = is_string($rawSkBendahara) && trim($rawSkBendahara) !== '' ? trim($rawSkBendahara) : '420/ 220 /HK /424.013/2024';

        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();
        $sumberDanaList = SumberDana::orderBy('kode')->get();

        // Riwayat penutupan kas tersimpan tahun anggaran terpilih (semua bulan &
        // semua sumber dana) utk tabel riwayat di bawah form.
        $riwayatPenutupan = $tahunAnggaranAktif !== null
            ? KasPenutupan::query()->where('tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->with('sumberDana')->orderBy('bulan')->orderBy('sumber_dana_id')->get()
            : collect();

        return compact(
            'profil', 'tahunAnggaranAktif', 'tahun', 'bulan', 'sumberDanaId', 'kasPenutupan',
            'tanggalPenutupan', 'tanggalPenutupanInput', 'tanggalPenutupanLalu', 'tanggalPenutupanLaluInput', 'hariPenutupan',
            'saldoAwal', 'penerimaanBulanIni', 'totalPenerimaanD', 'totalPengeluaranK', 'saldoBkuA',
            'rincianKertas', 'subtotalKertas', 'rincianLogam', 'subtotalLogam', 'subtotalFisikKas',
            'saldoBank', 'totalKasB', 'perbedaan', 'penjelasanPerbedaan',
            'skBupatiKepsek', 'skBupatiBendahara', 'tahunList', 'sumberDanaList',
            'totalPencairan', 'estimasiSaldoBank', 'riwayatPenutupan'
        );
    }
}
