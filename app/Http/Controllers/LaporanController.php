<?php

namespace App\Http\Controllers;

use App\Exports\BkuExport;
use App\Exports\RekapKuartalExport;
use App\Exports\RekapRekeningExport;
use App\Exports\RekapSiplahExport;
use App\Models\ExportJob;
use App\Models\MasterProgram;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class LaporanController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $tahunAnggaranAktif = TahunAnggaran::getActive();

        return view('laporan.index', compact('tahunAnggaranAktif'));
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

        $transaksis = TransaksiBku::with('rkasItem.program', 'rkasItem.kodeRekening.jenisBelanja')
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

            return $pdf->stream('BKU-' . $namaSekolah . '-' . $bulanLabel . '_' . $tahunLabel . '.pdf');
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

            return $pdf->stream('Rekap_Rekening-' . $namaSekolah . '-' . $bulanLabel . '_' . $tahunLabel . '.pdf');
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

            return $pdf->stream('Rekap_Kuartal-' . $namaSekolah . '-' . $qLabel . '_' . $tahunLabel . '.pdf');
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

            return $pdf->stream('Rekap_Siplah-' . $namaSekolah . '-' . $slug . '_' . $tahunLabel . '.pdf');
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
            ['bulan' => $bulan, 'tahunAnggaranId' => $tahunAnggaranAktif?->id, 'sumberDanaId' => $sumberDanaId],
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
            ['kuartal' => $kuartal, 'namaSekolah' => $namaSekolah, 'tahunAnggaranId' => $tahunAnggaranAktif?->id, 'sumberDanaId' => $sumberDanaId],
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

        $totalsQuery = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('jenis', 'pengeluaran')
            ->whereIn('bulan', $months)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId));

        $totals = (clone $totalsQuery)->selectRaw("
            COALESCE(SUM(jumlah), 0) as total,
            COALESCE(SUM(CASE WHEN metode_pengadaan = 'siplah' THEN jumlah ELSE 0 END), 0) as siplah,
            COALESCE(SUM(CASE WHEN metode_pengadaan = 'non_siplah' THEN jumlah ELSE 0 END), 0) as non_siplah
        ")->firstOrFail();

        $totalPengeluaran = (float) $totals->getAttribute('total');
        $totalSiplah = (float) $totals->getAttribute('siplah');
        $totalNonSiplah = (float) $totals->getAttribute('non_siplah');
        $totalBelumDiisi = $totalPengeluaran - $totalSiplah - $totalNonSiplah;
        $persenSiplah = $totalPengeluaran > 0 ? round(($totalSiplah / $totalPengeluaran) * 100, 1) : 0;
        $persenNonSiplah = $totalPengeluaran > 0 ? round(($totalNonSiplah / $totalPengeluaran) * 100, 1) : 0;

        $breakdownRows = DB::table('transaksi_bku as tb')
            ->leftJoin('rkas_item as ri', 'ri.id', '=', 'tb.rkas_item_id')
            ->leftJoin('master_kode_rekening as mkr', 'mkr.id', '=', 'ri.kode_rekening_id')
            ->leftJoin('jenis_belanja as jb', 'jb.id', '=', 'mkr.jenis_belanja_id')
            ->where('tb.jenis', 'pengeluaran')
            ->whereIn('tb.bulan', $months)
            ->when($sumberDanaId, fn($q) => $q->where('tb.sumber_dana_id', $sumberDanaId));

        if ($tahunAnggaranAktif) {
            $breakdownRows->where('tb.tahun_anggaran_id', $tahunAnggaranAktif->id);
        }

        $breakdownRows = $breakdownRows
            ->selectRaw("
                COALESCE(jb.nama, 'Tidak Terkategori') as jenis_belanja,
                COALESCE(SUM(tb.jumlah), 0) as total,
                COALESCE(SUM(CASE WHEN tb.metode_pengadaan = 'siplah' THEN tb.jumlah ELSE 0 END), 0) as siplah,
                COALESCE(SUM(CASE WHEN tb.metode_pengadaan = 'non_siplah' THEN tb.jumlah ELSE 0 END), 0) as non_siplah
            ")
            ->groupBy('jb.nama')
            ->orderBy('jb.nama')
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

        $transaksis = TransaksiBku::with('rkasItem.program', 'rkasItem.kodeRekening.jenisBelanja')
            ->where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        $saldoAwal = (float) TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->where('bulan', '<', $bulan)
            ->when($sumberDanaId, fn($q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("SUM(CASE WHEN LOWER(jenis) = 'penerimaan' THEN jumlah ELSE -jumlah END) as saldo")
            ->value('saldo');

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

        return compact('transaksis', 'profil', 'bulan', 'tahunAnggaranAktif',
            'saldoAwal', 'totalPenerimaan', 'totalPengeluaran', 'saldoAkhir', 'tahunList',
            'sumberDanaList', 'sumberDanaId');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\RkasItemBulan>|null $rencanaSub
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\TransaksiBku>|null $realisasiSub
     * @return \Illuminate\Support\Collection<int, \App\Models\RkasItem>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\RkasItem>
     */
    private function loadRekapRekeningItems(?TahunAnggaran $tahunAnggaranAktif, int $bulan, ?int $perPage = null, $rencanaSub = null, $realisasiSub = null): \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $sumberDanaId = request('sumber_dana_id');

        if ($rencanaSub === null) {
            $rencanaSub = RkasItemBulan::selectRaw('rkas_item_bulan.rkas_item_id, SUM(rkas_item_bulan.rencana) as total')
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'rkas_item_bulan.rkas_item_id')
                ->where('rkas_item_bulan.bulan', $bulan)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->groupBy('rkas_item_bulan.rkas_item_id');
        }

        if ($realisasiSub === null) {
            $realisasiSub = TransaksiBku::query()
                ->selectRaw('transaksi_bku.rkas_item_id, SUM(transaksi_bku.jumlah) as total')
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'transaksi_bku.rkas_item_id')
                ->where('transaksi_bku.jenis', 'pengeluaran')
                ->where('transaksi_bku.bulan', $bulan)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->groupBy('transaksi_bku.rkas_item_id');
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
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\TransaksiBku>|null $realisasiSub
     * @return \Illuminate\Support\Collection<int, \App\Models\RkasItem>|\Illuminate\Contracts\Pagination\LengthAwarePaginator<int, \App\Models\RkasItem>
     */
    private function loadKuartalItems(?TahunAnggaran $tahunAnggaranAktif, array $bulanMonths, ?int $perPage = null, $realisasiSub = null): \Illuminate\Support\Collection|\Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $cases = [];
        foreach ($bulanMonths as $i => $b) {
            $cases[] = "SUM(CASE WHEN transaksi_bku.bulan = {$b} THEN transaksi_bku.jumlah ELSE 0 END) as m{$i}";
        }
        $casesSql = implode(', ', $cases);

        $sumberDanaId = request('sumber_dana_id');

        if ($realisasiSub === null) {
            $realisasiSub = TransaksiBku::query()
                ->selectRaw("transaksi_bku.rkas_item_id, {$casesSql}, SUM(transaksi_bku.jumlah) as total_all")
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'transaksi_bku.rkas_item_id')
                ->where('transaksi_bku.jenis', 'pengeluaran')
                ->whereIn('transaksi_bku.bulan', $bulanMonths)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->groupBy('transaksi_bku.rkas_item_id');
        }

        $query = RkasItem::with('kodeRekening.jenisBelanja', 'program')
            ->select('rkas_item.*')
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
            ? TransaksiBku::query()
                ->selectRaw('transaksi_bku.rkas_item_id, SUM(transaksi_bku.jumlah) as total')
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'transaksi_bku.rkas_item_id')
                ->where('transaksi_bku.jenis', 'pengeluaran')
                ->where('transaksi_bku.bulan', $bulan)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('transaksi_bku.rkas_item_id')
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
            $cases[] = "SUM(CASE WHEN transaksi_bku.bulan = {$b} THEN transaksi_bku.jumlah ELSE 0 END) as m{$i}";
        }
        $casesSql = implode(', ', $cases);

        $realisasiSub = $tahunAnggaranAktif
            ? TransaksiBku::query()
                ->selectRaw("transaksi_bku.rkas_item_id, {$casesSql}, SUM(transaksi_bku.jumlah) as total_all")
                ->join('rkas_item as ri_sub', 'ri_sub.id', '=', 'transaksi_bku.rkas_item_id')
                ->where('transaksi_bku.jenis', 'pengeluaran')
                ->whereIn('transaksi_bku.bulan', $bulanMonths)
                ->where('ri_sub.tahun_anggaran_id', $tahunAnggaranAktif->id)
                ->when($sumberDanaId, fn($q) => $q->where('ri_sub.sumber_dana_id', $sumberDanaId))
                ->when($programId, fn($q) => $q->where('ri_sub.program_id', $programId))
                ->groupBy('transaksi_bku.rkas_item_id')
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
}
