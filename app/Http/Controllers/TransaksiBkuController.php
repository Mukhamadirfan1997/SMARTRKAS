<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Kwitansi;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\NotaBku;
use App\Models\Outbox;
use App\Models\PengaturanSekolah;
use App\Models\RkasItem;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Support\NomorDokumen;
use App\Support\NumberParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TransaksiBkuController extends Controller
{
    public function index(Request $request): View
    {
        $bulanRaw = $request->input('bulan', date('n'));
        $bulan = is_string($bulanRaw) || is_numeric($bulanRaw) ? (string) $bulanRaw : '';
        $tahunAnggaranAktif = TahunAnggaran::getActive();
        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();

        $tahunInput = $request->input('tahun');
        if ($tahunInput) {
            $tahunRecord = TahunAnggaran::where('tahun', $tahunInput)->first();
            if ($tahunRecord) {
                $tahunAnggaranAktif = $tahunRecord;
            }
        }
        $sumberDanas = SumberDana::orderBy('kode')->get();
        $sumberDanaId = $request->input('sumber_dana_id');

        $query = TransaksiBku::with('rkasItem.program', 'rkasItem.kodeRekening.jenisBelanja')
            ->where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->orderBy('tanggal')
            ->orderBy('id');

        if ($bulan !== '') {
            $query->where('bulan', is_numeric($bulan) ? (int) $bulan : 0);
        }

        if ($sumberDanaId) {
            $query->where('sumber_dana_id', $sumberDanaId);
        }

        $searchRaw = $request->input('search');
        $search = is_string($searchRaw) ? $searchRaw : '';
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('no_bukti', 'LIKE', "%{$search}%")
                  ->orWhere('uraian', 'LIKE', "%{$search}%")
                  ->orWhere('toko_penerima', 'LIKE', "%{$search}%");
            });
        }

        $transaksis = $query->paginate(50)->withQueryString();

        $saldoAwal = 0.0;
        $first = $transaksis->first();
        if ($first !== null) {
            $firstTanggal = Carbon::parse($first->tanggal)->toDateString();
            $saldoAwal = (float) TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
                ->when($sumberDanaId, function (Builder $q) use ($sumberDanaId): void {
                    $q->where('sumber_dana_id', $sumberDanaId);
                })
                ->where(function (Builder $q) use ($first, $firstTanggal): void {
                    $q->where('tanggal', '<', $firstTanggal)
                      ->orWhere(function (Builder $q2) use ($first, $firstTanggal): void {
                          $q2->where('tanggal', '=', $firstTanggal)
                             ->where('id', '<', $first->id);
                      });
                })
                ->selectRaw("COALESCE(SUM(CASE WHEN LOWER(jenis) = 'penerimaan' THEN jumlah ELSE -jumlah END), 0) as saldo")
                ->value('saldo');
        }

        $saldo = $saldoAwal;
        foreach ($transaksis as $transaksi) {
            $saldo += strtolower($transaksi->jenis) === 'penerimaan' ? $transaksi->jumlah : -$transaksi->jumlah;
            $transaksi->saldo_berjalan = $saldo;
        }

        $bulanQuery = $bulan !== '' ? (int) $bulan : null;
        $totals = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->when($bulanQuery, fn (Builder $q) => $q->where('bulan', $bulanQuery))
            ->when($sumberDanaId, fn (Builder $q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->selectRaw("
                COALESCE(SUM(CASE WHEN LOWER(jenis) = 'penerimaan' THEN jumlah ELSE 0 END), 0) as total_penerimaan,
                COALESCE(SUM(CASE WHEN LOWER(jenis) = 'pengeluaran' THEN jumlah ELSE 0 END), 0) as total_pengeluaran
            ")->firstOrFail();
        $totalPenerimaan = (float) $totals->getAttribute('total_penerimaan');
        $totalPengeluaran = (float) $totals->getAttribute('total_pengeluaran');
        $selisihBulanIni = $totalPenerimaan - $totalPengeluaran;
        $saldoAkhir = $saldo;

        $belumMetodePengadaan = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->when($sumberDanaId, fn (Builder $q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->where('jenis', 'pengeluaran')
            ->whereNull('metode_pengadaan')
            ->count();

        $belumCetakKwitansi = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->when($sumberDanaId, fn (Builder $q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->where('jenis', 'pengeluaran')
            ->whereDoesntHave('kwitansi')
            ->count();

        $countOverride = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id)
            ->when($bulanQuery, fn (Builder $q) => $q->where('bulan', $bulanQuery))
            ->when($sumberDanaId, fn (Builder $q) => $q->where('sumber_dana_id', $sumberDanaId))
            ->whereNotNull('override_note')
            ->where('override_note', '!=', '')
            ->count();

        return view('transaksi-bku.index', compact(
            'transaksis', 'bulan', 'totalPenerimaan', 'totalPengeluaran', 'saldoAkhir', 'selisihBulanIni',
            'belumMetodePengadaan', 'belumCetakKwitansi', 'countOverride', 'tahunAnggaranAktif', 'tahunList',
            'sumberDanas', 'sumberDanaId'
        ));
    }

    public function create(): View
    {
        $npsn = PengaturanSekolah::get()->npsn ?? '00000000';
        $tahunAnggaranRecord = TahunAnggaran::where('status', true)->first(['id']);
        $tahunAnggaranId = $tahunAnggaranRecord ? $tahunAnggaranRecord->id : '';

        $countPenerimaan = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranId)
            ->where('jenis', 'penerimaan')->count() + 1;
        $countPengeluaran = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranId)
            ->where('jenis', 'pengeluaran')->count() + 1;

        $kegiatans = MasterProgram::orderBy('kode')->get();
        $kodeRekenings = MasterKodeRekening::orderBy('kode')->get();

        $pickerInitial = null;
        $oldItemId = old('rkas_item_id');
        if (is_string($oldItemId) && $oldItemId !== '') {
            $item = RkasItem::with('program', 'kodeRekening')->find($oldItemId);
            if ($item) {
                $bulanPilihan = (int) Carbon::parse((string) old('tanggal', now()->toDateString()))->month;
                $pickerInitial = [
                    'id' => $item->id,
                    'text' => $item->no_urut . '. ' . $item->uraian,
                    'program' => $item->program?->nama,
                    'kode' => $item->kodeRekening?->kode,
                    'tarif' => (float) $item->tarif,
                    'satuan' => $item->satuan,
                    'sisa' => $item->sisaKumulatifSd($bulanPilihan),
                    'bulan' => $bulanPilihan,
                ];
            }
        }

        return view('transaksi-bku.create', compact(
            'npsn', 'countPenerimaan', 'countPengeluaran', 'pickerInitial',
            'kegiatans', 'kodeRekenings'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $rawItems = $request->input('items');

        if (is_array($rawItems)) {
            $checked = array_values(array_filter($rawItems, static fn (mixed $v): bool => is_array($v) && !empty($v['rkas_item_id'])));

            if (count($checked) >= 2) {
                // 2+ item: alur Nota multi-item (ALL-OR-NOTHING + flatten), tanpa override.
                // Reuse penuh logika NotaBkuController::storeFromItems() — bukan reimplementasi.
                return (new NotaBkuController)->storeFromItems($request);
            }

            if (count($checked) === 1) {
                // Tepat 1 item: perilaku PERSIS form single-item lama — transaksi langsung
                // (tanpa NotaBku), override & kunci kwitansi tetap berlaku.
                $first = $checked[0];
                $qty = NumberParser::decimal($first['qty'] ?? null);
                $harga = NumberParser::rupiah($first['harga'] ?? null);

                $request->merge([
                    'rkas_item_id' => (string) $first['rkas_item_id'],
                    'volume' => $qty,
                    'jumlah' => (string) round(((float) $qty) * ((float) $harga), 2),
                    'satuan' => isset($first['satuan']) && (string) $first['satuan'] !== ''
                        ? (string) $first['satuan']
                        : $request->input('satuan'),
                    'jenis' => 'pengeluaran',
                ]);
            }
        }

        return $this->storeSingleItem($request);
    }

    /**
     * Logika store() single-item (jalur lama), di-extract apa adanya tanpa perubahan
     * perilaku: dipakai oleh form lama (rkas_item_id + jumlah + volume langsung)
     * DAN form pengeluaran baru dengan tepat 1 item dicentang. Override anggaran,
     * kunci kwitansi saat over-budget, auto no_bukti, audit & outbox semua tetap
     * berjalan identik.
     */
    private function storeSingleItem(Request $request): RedirectResponse
    {
        $request->merge([
            'jumlah' => NumberParser::rupiah($request->input('jumlah')),
            'volume' => NumberParser::decimal($request->input('volume')),
        ]);

        $validated = $request->validate([
            'rkas_item_id' => 'nullable|exists:rkas_item,id',
            'tanggal' => 'required|date',
            'no_bukti' => 'nullable|string|max:255',
            'jenis' => 'required|in:penerimaan,pengeluaran',
            'jumlah' => 'required|numeric|gt:0',
            'toko_penerima' => 'nullable|string|max:255',
            'metode_pengadaan' => 'nullable|string|in:siplah,non_siplah',
            'no_invoice_siplah' => 'nullable|required_if:metode_pengadaan,siplah|string|max:255',
            'volume' => 'nullable|numeric|min:0',
            'satuan' => 'nullable|string|max:50',
            'uraian' => 'nullable|string',
            'override_anggaran' => 'nullable|in:1,on,true',
            'override_note' => 'nullable|required_if:override_anggaran,1|string|min:10|max:500',
        ], [
            'no_invoice_siplah.required_if' => 'Nomor Invoice SIPLah wajib diisi saat metode pengadaan SIPLah.',
        ]);

        $tanggal = (string) $validated['tanggal'];
        $jenis = (string) $validated['jenis'];
        $jumlah = (float) $validated['jumlah'];
        $rkasItemId = $validated['rkas_item_id'] ?? null;
        $noBukti = trim((string) ($validated['no_bukti'] ?? ''));
        $overrideNote = isset($validated['override_note']) ? trim($validated['override_note']) : '';

        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        $validated['created_by'] = $user->id;
        $validated['bulan'] = (int) Carbon::parse($tanggal)->month;
        $taRec = TahunAnggaran::where('status', true)->first(['id']);
        $validated['tahun_anggaran_id'] = $taRec ? $taRec->id : '';

        if ($noBukti === '' || TransaksiBku::where('no_bukti', $noBukti)->exists()) {
            $noBukti = NomorDokumen::noBukti($jenis, $tanggal);
            $validated['no_bukti'] = $noBukti;
        }

        if (!empty($rkasItemId)) {
            $rkasItem = RkasItem::find($rkasItemId);
            $validated['sumber_dana_id'] = $rkasItem?->sumber_dana_id;
            if ($rkasItem && empty($request->input('satuan'))) {
                $validated['satuan'] = $rkasItem->satuan;
            }
        }

        $isOverriding = false;

        if ($jenis === 'pengeluaran' && !empty($rkasItemId)) {
            $rkasItem = RkasItem::with('bulanRencana')->findOrFail($rkasItemId);

            $rencanaKumulatif = $rkasItem->bulanRencana->where('bulan', '<=', $validated['bulan'])->sum('rencana');
            $realisasiKumulatif = $rkasItem->realisasiKumulatifSd((int) $validated['bulan']);

            $sisaBulanBerjalan = $rencanaKumulatif - $realisasiKumulatif;

            $isOverriding = $request->boolean('override_anggaran') && $overrideNote !== '';

            if ($jumlah > $sisaBulanBerjalan && !$isOverriding) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Gagal: Nominal Rp ' . number_format($jumlah, 0, ',', '.') .
                        ' melebihi sisa anggaran s.d. bulan ' . $validated['bulan'] . ' (Rp ' . number_format($sisaBulanBerjalan, 0, ',', '.') .
                        '). Gunakan opsi "Override Sisa Anggaran" jika ingin melanjutkan (wajib isi catatan, kwitansi akan terkunci).',
                ]);
            }

            if ($isOverriding) {
                AuditLog::record('transaksi_bku', 'override_anggaran', [
                    'no_bukti' => $noBukti,
                    'jumlah' => $jumlah,
                    'sisa_anggaran' => $sisaBulanBerjalan,
                    'catatan' => $overrideNote,
                ], null, $user->id);
            }
        }

        $validated['override_note'] = $isOverriding ? $overrideNote : null;
        unset($validated['override_anggaran']);

        $transaksi = TransaksiBku::create($validated);

        AuditLog::record('transaksi_bku', 'create', [
            'no_bukti' => $transaksi->no_bukti,
            'jenis' => $transaksi->jenis,
            'jumlah' => (float) $transaksi->jumlah,
            'override' => !empty($transaksi->override_note),
        ], null, $user->id);

        Outbox::record('TransaksiBku', $transaksi->id, 'create', $validated);

        Cache::increment('dash_ver_' . $user->id);

        if (!empty($transaksi->override_note)) {
            return redirect()->route('transaksi-bku.index')->with('success', 'Transaksi berhasil ditambahkan dengan OVERRIDE anggaran. PENTING: Segera ajukan pergeseran / Perubahan Anggaran (PA) pada item RKAS terkait dan laporkan ke pengelola anggaran. Kwitansi transaksi ini terkunci sampai penyesuaian dilakukan.');
        }

        return redirect()->route('transaksi-bku.index')->with('success', 'Transaksi berhasil ditambahkan.');
    }

    /**
     * Buat no bukti (BBU/BPU) secara otomatis di sisi server bila field kosong
     * atau duplikat. Dipakai sebagai fallback agar transaksi tidak pernah gagal
     * hanya karena nomor bukti yang dihasilkan JavaScript bentrok.
     */
    public function edit(TransaksiBku $transaksiBku): View
    {
        $transaksiBku->load('rkasItem.program', 'rkasItem.kodeRekening');
        $selectedRkas = null;
        if ($transaksiBku->rkasItem) {
            $item = $transaksiBku->rkasItem;
            $bulanTransaksi = (int) Carbon::parse($transaksiBku->tanggal)->month;
            $selectedRkas = [
                'id' => $item->id,
                'text' => $item->no_urut . '. ' . $item->uraian,
                'program' => $item->program?->nama,
                'kode' => $item->kodeRekening?->kode,
                'tarif' => (float) $item->tarif,
                'satuan' => $item->satuan,
                'sisa' => $item->sisaKumulatifSd($bulanTransaksi),
                'bulan' => $bulanTransaksi,
            ];
        }

        return view('transaksi-bku.edit', compact('transaksiBku', 'selectedRkas'));
    }

    public function update(Request $request, TransaksiBku $transaksiBku): RedirectResponse
    {
        $request->merge([
            'jumlah' => NumberParser::rupiah($request->input('jumlah')),
            'volume' => NumberParser::decimal($request->input('volume')),
        ]);

        $validated = $request->validate([
            'rkas_item_id' => 'nullable|exists:rkas_item,id',
            'tanggal' => 'required|date',
            'no_bukti' => ['required', Rule::unique('transaksi_bku', 'no_bukti')->ignore($transaksiBku->id)->whereNull('deleted_at')],
            'jenis' => 'required|in:penerimaan,pengeluaran',
            'jumlah' => 'required|numeric|gt:0',
            'toko_penerima' => 'nullable|string|max:255',
            'metode_pengadaan' => 'nullable|string|in:siplah,non_siplah',
            'no_invoice_siplah' => 'nullable|required_if:metode_pengadaan,siplah|string|max:255',
            'volume' => 'nullable|numeric|min:0',
            'satuan' => 'nullable|string|max:50',
            'uraian' => 'nullable|string',
        ], [
            'no_invoice_siplah.required_if' => 'Nomor Invoice SIPLah wajib diisi saat metode pengadaan SIPLah.',
        ]);

        $tanggal = (string) $validated['tanggal'];
        $jenis = (string) $validated['jenis'];
        $jumlah = (float) $validated['jumlah'];
        $rkasItemId = $validated['rkas_item_id'] ?? null;

        $validated['bulan'] = (int) Carbon::parse($tanggal)->month;
        $taRec = TahunAnggaran::where('status', true)->first(['id']);
        $validated['tahun_anggaran_id'] = $taRec ? $taRec->id : '';

        if (!empty($rkasItemId)) {
            $rkasItem = RkasItem::find($rkasItemId);
            $validated['sumber_dana_id'] = $rkasItem?->sumber_dana_id;
            if ($rkasItem && empty($request->input('satuan'))) {
                $validated['satuan'] = $rkasItem->satuan;
            }
        }

        if ($jenis === 'pengeluaran' && !empty($rkasItemId)) {
            $rkasItem = RkasItem::with('bulanRencana')->findOrFail($rkasItemId);

            $rencanaKumulatif = $rkasItem->bulanRencana->where('bulan', '<=', $validated['bulan'])->sum('rencana');
            $realisasiKumulatif = $rkasItem->realisasiKumulatifSd((int) $validated['bulan'], (string) $transaksiBku->id);

            $sisaBulanBerjalan = $rencanaKumulatif - $realisasiKumulatif;

            if ($jumlah > $sisaBulanBerjalan) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Gagal Update: Nominal Rp ' . number_format($jumlah, 0, ',', '.') .
                        ' melebihi sisa anggaran s.d. bulan ' . $validated['bulan'] . ' (Rp ' . number_format($sisaBulanBerjalan, 0, ',', '.') . ').',
                ]);
            }
        }

        $dataLama = [
            'no_bukti' => $transaksiBku->no_bukti,
            'jenis' => $transaksiBku->jenis,
            'jumlah' => (float) $transaksiBku->jumlah,
        ];

        $transaksiBku->update($validated);

        AuditLog::record('transaksi_bku', 'update', [
            'no_bukti' => $transaksiBku->no_bukti,
            'jenis' => $transaksiBku->jenis,
            'jumlah' => (float) $transaksiBku->jumlah,
        ], $dataLama);

        Outbox::record('TransaksiBku', $transaksiBku->id, 'update', $validated);

        Cache::increment('dash_ver_' . auth()->id());

        return redirect()->route('transaksi-bku.index')->with('success', 'Transaksi berhasil diupdate.');
    }

    public function destroy(Request $request, TransaksiBku $transaksiBku): RedirectResponse
    {
        $rawNote = $request->input('delete_note');
        $deleteNote = is_string($rawNote) ? trim($rawNote) : '';
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        $notaId = $transaksiBku->nota_bku_id;

        if ($notaId !== null) {
            $nota = NotaBku::withTrashed()->find($notaId);
            if ($nota !== null && !$nota->trashed()) {
                $count = (new NotaBkuController)->deleteNotaWithTransaksis($nota);

                return back()->with('success', 'Transaksi dihapus beserta nota ' . $nota->no_nota . ' (' . $count . ' transaksi terkait).');
            }
        }

        $noBukti = $transaksiBku->no_bukti;
        $jumlah = $transaksiBku->jumlah;
        $id = $transaksiBku->id;

        $transaksiBku->delete();

        AuditLog::record('transaksi_bku', 'delete', [
            'no_bukti' => $noBukti,
            'jumlah' => $jumlah,
            'catatan' => $deleteNote !== '' ? $deleteNote : null,
        ], null, $user->id);

        Outbox::record('TransaksiBku', $id, 'delete', [
            'no_bukti' => $noBukti,
            'jumlah' => $jumlah,
            'catatan' => $deleteNote !== '' ? $deleteNote : null,
        ]);

        Cache::increment('dash_ver_' . $user->id);

        return back()->with('success', 'Transaksi dihapus.');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return back()->with('error', 'Sesi berakhir. Silakan login ulang.');
        }

        $tahunAnggaranAktif = TahunAnggaran::getActive();

        $tahunInput = $request->input('tahun');
        if ($tahunInput) {
            $tahunRecord = TahunAnggaran::where('tahun', $tahunInput)->first();
            if ($tahunRecord) {
                $tahunAnggaranAktif = $tahunRecord;
            }
        }

        $bulanRaw = $request->input('bulan');
        $bulan = is_numeric($bulanRaw) ? (int) $bulanRaw : 0;
        $sumberDanaIdRaw = $request->input('sumber_dana_id');
        $sumberDanaId = is_string($sumberDanaIdRaw) && $sumberDanaIdRaw !== '' ? $sumberDanaIdRaw : null;
        $searchRaw = $request->input('search');
        $search = is_string($searchRaw) ? $searchRaw : '';
        $rawNote = $request->input('alasan');
        $note = is_string($rawNote) ? trim($rawNote) : '';

        $query = TransaksiBku::where('tahun_anggaran_id', $tahunAnggaranAktif?->id);
        if ($bulan > 0) {
            $query->where('bulan', $bulan);
        }
        if ($sumberDanaId !== null) {
            $query->where('sumber_dana_id', $sumberDanaId);
        }
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('no_bukti', 'LIKE', "%{$search}%")
                  ->orWhere('uraian', 'LIKE', "%{$search}%")
                  ->orWhere('toko_penerima', 'LIKE', "%{$search}%");
            });
        }

        $transaksis = $query->get();
        $count = $transaksis->count();

        if ($count === 0) {
            return back()->with('error', 'Tidak ada transaksi yang cocok untuk dihapus.');
        }

        $noBuktis = [];
        $notaIds = $transaksis
            ->filter(static fn (TransaksiBku $t): bool => $t->nota_bku_id !== null)
            ->pluck('nota_bku_id')
            ->unique()
            ->values();
        $notas = NotaBku::withTrashed()->whereIn('id', $notaIds)->whereNull('deleted_at')->get();

        foreach ($transaksis as $transaksi) {
            if ($transaksi->nota_bku_id !== null) {
                continue;
            }
            $noBuktis[] = $transaksi->no_bukti;
            $transaksi->delete();
            Outbox::record('TransaksiBku', $transaksi->id, 'delete', [
                'no_bukti' => $transaksi->no_bukti,
                'jumlah' => $transaksi->jumlah,
                'catatan' => $note !== '' ? $note : null,
            ]);
        }

        foreach ($notas as $nota) {
            (new NotaBkuController)->deleteNotaWithTransaksis($nota);
        }

        AuditLog::record('transaksi_bku', 'delete_bulk', [
            'jumlah_transaksi' => $count,
            'jumlah_nota' => $notas->count(),
            'no_bukti' => array_slice($noBuktis, 0, 50),
            'catatan' => $note !== '' ? $note : null,
        ], null, $user->id);

        Cache::increment('dash_ver_' . $user->id);

        return back()->with('success', $count . ' transaksi dihapus' . ($notas->count() > 0 ? ' termasuk ' . $notas->count() . ' nota terkait.' : '.'));
    }

    public function cetakKwitansi(TransaksiBku $transaksiBku): Response|RedirectResponse
    {
        if ($transaksiBku->masihOverBudget()) {
            return redirect()->route('transaksi-bku.index')->with('error', 'Kwitansi transaksi ' . $transaksiBku->no_bukti .
                ' tidak dapat dicetak: transaksi dibuat dengan OVERRIDE anggaran dan belum dilakukan penyesuaian. Segera lakukan pergeseran / Perubahan Anggaran (PA) pada item RKAS terkait.');
        }

        $transaksiBku->load('rkasItem.program', 'rkasItem.kodeRekening');
        $profil = PengaturanSekolah::get();

        $pdf = Pdf::loadView('transaksi-bku.kwitansi', compact('transaksiBku', 'profil'))
            ->setPaper([0, 0, 609.4488, 935.433], 'portrait');

        $safeFileName = str_replace(['/', '\\'], '-', $transaksiBku->no_bukti);
        $fileName = 'kwitansi-' . $safeFileName . '.pdf';
        $filePath = 'kwitansi/' . $fileName;

        Storage::disk('public')->put($filePath, $pdf->output());

        $kwitansi = $transaksiBku->kwitansi()->firstOrNew([]);
        $kwitansi->nomor = $transaksiBku->no_bukti;
        $kwitansi->dicetak_pada = now();
        $kwitansi->file_pdf_path = $filePath;
        $kwitansi->save();

        return $pdf->stream($fileName);
    }

    /** @return Response|RedirectResponse */
    public function cetakKwitansiBatch(Request $request): Response|RedirectResponse
    {
        $ids = $request->input('ids', []);

        if (empty($ids)) {
            return back()->with('error', 'Pilih minimal satu transaksi untuk dicetak.');
        }

        $transaksis = TransaksiBku::with('rkasItem.program', 'rkasItem.kodeRekening')
            ->whereIn('id', $ids)
            ->orderBy('tanggal')
            ->orderBy('id')
            ->get();

        if ($transaksis->isEmpty()) {
            return back()->with('error', 'Data transaksi tidak ditemukan.');
        }

        $transaksis->load(['rkasItem.bulanRencana', 'rkasItem.transaksiBkus']);
        $blocked = $transaksis->filter(fn (TransaksiBku $t): bool => $t->masihOverBudget());
        if ($blocked->isNotEmpty()) {
            $noBuktis = $blocked->map(fn (TransaksiBku $t): string => $t->no_bukti)->implode(', ');

            return back()->with('error', 'Dibatalkan: kwitansi ' . $blocked->count() . ' transaksi tidak dapat dicetak karena masih OVERRIDE anggaran dan belum ada penyesuaian (pergeseran / Perubahan Anggaran). No. bukti: ' . $noBuktis . '.');
        }

        $profil = PengaturanSekolah::get();

        $pdf = Pdf::loadView('transaksi-bku.kwitansi-batch', compact('transaksis', 'profil'))
            ->setPaper([0, 0, 609.4488, 935.433], 'portrait');

        $fileName = 'kwitansi-batch-' . now()->format('YmdHis') . '.pdf';

        foreach ($transaksis as $transaksi) {
            $kwitansi = $transaksi->kwitansi()->firstOrNew([]);
            $kwitansi->nomor = $transaksi->no_bukti;
            $kwitansi->dicetak_pada = now();
            $kwitansi->file_pdf_path = 'kwitansi/' . $fileName;
            $kwitansi->save();
        }

        return $pdf->stream($fileName);
    }
}
