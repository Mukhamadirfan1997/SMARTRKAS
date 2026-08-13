<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\NotaBku;
use App\Models\Outbox;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Support\NomorDokumen;
use App\Support\NumberParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class NotaBkuController extends Controller
{
    public function index(): View
    {
        $notas = NotaBku::with('kegiatan', 'sumberDana')
            ->withCount('items')
            ->withSum('items as total_item', 'subtotal')
            ->latest('tanggal')
            ->paginate(20)
            ->withQueryString();

        return view('nota-bku.index', compact('notas'));
    }

    public function create(): View
    {
        $kegiatans = MasterProgram::orderBy('kode')->get();
        $kodeRekenings = MasterKodeRekening::orderBy('kode')->get();

        return view('nota-bku.create', compact('kegiatans', 'kodeRekenings'));
    }

    /**
     * Daftar item RKAS anggota kegiatan tertentu + sisa anggaran s.d. bulan
     * (dipakai form nota multi-item via AJAX).
     */
    public function items(Request $request): JsonResponse
    {
        $kegiatanId = $request->input('kegiatan_id');
        $kodeRekeningId = $request->input('kode_rekening_id');
        $bulanRaw = $request->input('bulan');
        $bulan = is_numeric($bulanRaw) ? (int) $bulanRaw : null;

        $taRec = TahunAnggaran::where('status', true)->first(['id']);

        $query = RkasItem::with('sumberDana')->orderBy('no_urut');
        if (is_string($kegiatanId) && $kegiatanId !== '') {
            $query->where('program_id', $kegiatanId);
        }
        if (is_string($kodeRekeningId) && $kodeRekeningId !== '') {
            $query->where('kode_rekening_id', $kodeRekeningId);
        }
        if ($taRec !== null) {
            $query->where('tahun_anggaran_id', $taRec->id);
        }

        $results = $query->get()->map(function (RkasItem $item) use ($bulan): array {
            return [
                'id' => $item->id,
                'no_urut' => $item->no_urut,
                'uraian' => $item->uraian,
                'tarif' => (float) $item->tarif,
                'satuan' => (string) ($item->satuan ?? ''),
                'kode_rekening_id' => (string) ($item->kode_rekening_id ?? ''),
                'sumber_dana' => $item->sumberDana
                    ? $item->sumberDana->kode . ' ' . $item->sumberDana->nama
                    : '',
                'sisa' => $bulan !== null ? $item->sisaKumulatifSd($bulan) : (float) $item->jumlah,
            ];
        });

        return response()->json(['results' => $results]);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->storeFromItems($request);
    }

    /**
     * Simpan nota multi-item: validasi (kegiatan & sumber dana seragam),
     * guard anggaran ALL-OR-NOTHING, lalu flatten menjadi 1 TransaksiBku per item
     * di dalam satu transaksi database.
     * Method reusable — dipanggil langsung dari TransaksiBkuController::store()
     * saat form pengeluaran dicentang 2+ item (reuse, bukan reimplementasi).
     */
    public function storeFromItems(Request $request): RedirectResponse
    {
        $items = $request->input('items', []);
        $normalized = [];

        if (is_array($items)) {
            foreach ($items as $key => $value) {
                $value = is_array($value) ? $value : [];
                $normalized[$key] = [
                    'rkas_item_id' => isset($value['rkas_item_id']) ? (string) $value['rkas_item_id'] : null,
                    'qty' => NumberParser::decimal($value['qty'] ?? null),
                    'harga' => NumberParser::rupiah($value['harga'] ?? null),
                    'satuan' => isset($value['satuan']) && (string) $value['satuan'] !== '' ? (string) $value['satuan'] : null,
                ];
            }
        }

        $request->merge(['items' => $normalized]);

        $validated = $request->validate([
            'tanggal' => 'required|date',
            'kegiatan_id' => 'required|exists:master_program,id',
            'kode_rekening_id' => 'required|exists:master_kode_rekening,id',
            'toko_penerima' => 'nullable|string|max:255',
            'metode_pengadaan' => 'nullable|string|in:siplah,non_siplah',
            'no_invoice_siplah' => 'nullable|required_if:metode_pengadaan,siplah|string|max:255',
            'uraian' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.rkas_item_id' => 'required|distinct|exists:rkas_item,id',
            'items.*.qty' => 'required|numeric|gt:0',
            'items.*.harga' => 'required|numeric|gt:0',
            'items.*.satuan' => 'nullable|string|max:50',
        ], [
            'no_invoice_siplah.required_if' => 'Nomor Invoice SIPLah wajib diisi saat metode pengadaan SIPLah.',
            'items.required' => 'Pilih minimal satu item belanja.',
            'items.min' => 'Pilih minimal satu item belanja.',
            'items.*.rkas_item_id.distinct' => 'Ada item yang sama dipilih lebih dari satu kali.',
            'items.*.qty.gt' => 'Jumlah (qty) setiap item harus lebih dari 0.',
            'items.*.harga.gt' => 'Harga satuan setiap item harus lebih dari 0.',
        ]);

        $tanggal = (string) $validated['tanggal'];
        $bulan = (int) Carbon::parse($tanggal)->month;
        $kegiatanId = (string) $validated['kegiatan_id'];
        $kodeRekeningId = (string) $validated['kode_rekening_id'];
        $tokoPenerima = isset($validated['toko_penerima']) ? trim((string) $validated['toko_penerima']) : null;
        $metodePengadaan = $validated['metode_pengadaan'] ?? null;
        $noInvoice = isset($validated['no_invoice_siplah']) ? trim((string) $validated['no_invoice_siplah']) : null;
        $uraian = isset($validated['uraian']) ? trim((string) $validated['uraian']) : null;

        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        $taRec = TahunAnggaran::where('status', true)->first(['id']);
        if ($taRec === null) {
            return back()->withInput()->with('error', 'Belum ada tahun anggaran aktif. Atur tahun anggaran aktif terlebih dahulu.');
        }
        $tahunAnggaranId = $taRec->id;

        /** @var array<int, array<string, mixed>> $rawItems */
        $rawItems = (array) $validated['items'];
        $itemIds = collect($rawItems)->pluck('rkas_item_id')
            ->map(static fn (mixed $v): string => (string) $v)
            ->unique()
            ->values();

        /** @var \Illuminate\Support\Collection<string, RkasItem> $rkasItems */
        $rkasItems = RkasItem::whereIn('id', $itemIds)->get()->keyBy('id');

        foreach ($itemIds as $id) {
            $item = $rkasItems->get((string) $id);
            if ($item !== null && $item->program_id !== $kegiatanId) {
                throw ValidationException::withMessages([
                    'items' => 'Item "' . $item->uraian . '" bukan bagian dari kegiatan yang dipilih. Buat nota terpisah untuk kegiatan lain.',
                ]);
            }
            if ($item !== null && $item->tahun_anggaran_id !== $tahunAnggaranId) {
                throw ValidationException::withMessages([
                    'items' => 'Item "' . $item->uraian . '" bukan bagian dari tahun anggaran aktif. Hanya item tahun anggaran aktif yang boleh dibuatkan nota.',
                ]);
            }
            if ($item !== null && $item->kode_rekening_id !== $kodeRekeningId) {
                throw ValidationException::withMessages([
                    'items' => 'Item "' . $item->uraian . '" bukan bagian dari kode rekening yang dipilih. Pilih kode rekening yang sesuai dengan item belanja.',
                ]);
            }
        }

        $sumberDanaId = null;
        foreach ($itemIds as $id) {
            $item = $rkasItems->get((string) $id);
            if ($item === null) {
                continue;
            }
            if ($item->sumber_dana_id === null) {
                throw ValidationException::withMessages([
                    'items' => 'Item "' . $item->uraian . '" belum memiliki sumber dana. Lengkapi data item RKAS terlebih dahulu.',
                ]);
            }
            if ($sumberDanaId === null) {
                $sumberDanaId = $item->sumber_dana_id;
            } elseif ($sumberDanaId !== $item->sumber_dana_id) {
                throw ValidationException::withMessages([
                    'items' => 'Item belanja berasal dari sumber dana berbeda. Satu nota hanya boleh untuk satu sumber dana; buat nota terpisah jika sumber dananya berbeda.',
                ]);
            }
        }

        if ($sumberDanaId === null) {
            throw ValidationException::withMessages(['items' => 'Tidak ada item belanja yang valid.']);
        }

        /** @var list<array{item: RkasItem, qty: float, harga: float, subtotal: float, satuan: string}> $prepared */
        $prepared = [];
        $overBudget = [];
        $total = 0.0;

        foreach ($rawItems as $entry) {
            $itemId = (string) ($entry['rkas_item_id'] ?? '');
            $item = $rkasItems->get($itemId);
            if ($item === null) {
                continue;
            }

            $qty = (float) $entry['qty'];
            $harga = (float) $entry['harga'];
            $subtotal = round($qty * $harga, 2);
            $satuan = isset($entry['satuan']) && (string) $entry['satuan'] !== ''
                ? (string) $entry['satuan']
                : (string) ($item->satuan ?? '');
            $sisa = $item->sisaKumulatifSd($bulan);

            if ($subtotal > $sisa) {
                $overBudget[] = 'Item ' . $item->no_urut . '. ' . $item->uraian
                    . ' (Rp ' . number_format($subtotal, 0, ',', '.') . ') melebihi sisa anggaran s.d. bulan ' . $bulan
                    . ' (sisa Rp ' . number_format($sisa, 0, ',', '.') . ')';
            }

            $prepared[] = [
                'item' => $item,
                'qty' => $qty,
                'harga' => $harga,
                'subtotal' => $subtotal,
                'satuan' => $satuan,
            ];
            $total += $subtotal;
        }

        if ($prepared === []) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu item belanja yang valid.']);
        }

        if ($overBudget !== []) {
            $detail = implode('. ', $overBudget);
            throw ValidationException::withMessages([
                'items' => 'Nota DITOLAK karena ada item yang tidak cukup anggaran; SELURUH nota dibatalkan dan tidak ada transaksi yang disimpan. ' . $detail . '. Sesuaikan rencana anggaran (pergeseran / Perubahan Anggaran) sebelum membuat nota.',
            ]);
        }

        $nota = DB::transaction(function () use (
            $prepared,
            $bulan,
            $kegiatanId,
            $kodeRekeningId,
            $sumberDanaId,
            $tahunAnggaranId,
            $tanggal,
            $tokoPenerima,
            $metodePengadaan,
            $noInvoice,
            $uraian,
            $total,
            $user,
        ) {
            $noNota = NomorDokumen::noNota($tanggal);

            $nota = NotaBku::create([
                'no_nota' => $noNota,
                'tanggal' => $tanggal,
                'bulan' => $bulan,
                'kegiatan_id' => $kegiatanId,
                'kode_rekening_id' => $kodeRekeningId,
                'sumber_dana_id' => $sumberDanaId,
                'tahun_anggaran_id' => $tahunAnggaranId,
                'toko_penerima' => $tokoPenerima,
                'metode_pengadaan' => $metodePengadaan,
                'no_invoice_siplah' => $noInvoice,
                'uraian' => $uraian,
                'created_by' => $user->id,
            ]);

            foreach ($prepared as $i => $entry) {
                $item = $entry['item'];

                $nota->items()->create([
                    'rkas_item_id' => $item->id,
                    'urutan' => $i + 1,
                    'jumlah' => $entry['qty'],
                    'satuan' => $entry['satuan'],
                    'harga_satuan' => $entry['harga'],
                    'subtotal' => $entry['subtotal'],
                ]);
            }

            // Satu nota = satu transaksi pengeluaran (total nota), bukan satu
            // transaksi per item. Rincian item tetap tersimpan di nota_bku_item
            // dan dipakai sebagai sumber realisasi per item (lihat RealisasiQuery).
            $uraianTransaksi = $uraian !== null && $uraian !== ''
                ? $uraian
                : 'Nota belanja ' . $noNota;

            TransaksiBku::create([
                'nota_bku_id' => $nota->id,
                'rkas_item_id' => null,
                'tahun_anggaran_id' => $tahunAnggaranId,
                'sumber_dana_id' => $sumberDanaId,
                'tanggal' => $tanggal,
                'bulan' => $bulan,
                'no_bukti' => NomorDokumen::noBukti('pengeluaran', $tanggal),
                'jenis' => 'pengeluaran',
                'jumlah' => round($total, 2),
                'volume' => null,
                'satuan' => null,
                'toko_penerima' => $tokoPenerima,
                'metode_pengadaan' => $metodePengadaan,
                'no_invoice_siplah' => $noInvoice,
                'uraian' => $uraianTransaksi,
                'created_by' => $user->id,
            ]);

            return $nota;
        });

        AuditLog::record('nota_bku', 'create', [
            'no_nota' => $nota->no_nota,
            'kegiatan' => $kegiatanId,
            'sumber_dana' => $sumberDanaId,
            'jumlah_item' => count($prepared),
            'total' => round($total, 2),
        ], null, $user->id);

        Outbox::record('NotaBku', $nota->id, 'create', [
            'no_nota' => $nota->no_nota,
            'tanggal' => $tanggal,
            'bulan' => $bulan,
            'kegiatan' => $kegiatanId,
            'jumlah_item' => count($prepared),
            'total' => round($total, 2),
        ]);

        foreach ($nota->transaksiBkus as $transaksi) {
            Outbox::record('TransaksiBku', $transaksi->id, 'create', [
                'no_bukti' => $transaksi->no_bukti,
                'jumlah' => (float) $transaksi->jumlah,
                'nota' => $nota->no_nota,
            ]);
        }

        Cache::increment('dash_ver_' . $user->id);

        return redirect()->route('nota-bku.show', $nota)->with('success', 'Nota ' . $nota->no_nota . ' berhasil disimpan. ' . count($prepared) . ' item dibukukan sebagai 1 transaksi pengeluaran.');
    }

    public function show(NotaBku $notaBku): View
    {
        $notaBku->load('items.rkasItem', 'kegiatan', 'sumberDana', 'tahunAnggaran', 'createdBy');

        $total = (float) $notaBku->items->sum('subtotal');

        return view('nota-bku.show', compact('notaBku', 'total'));
    }

    public function destroy(NotaBku $notaBku): RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return redirect()->route('nota-bku.index')->with('error', 'Sesi berakhir. Silakan login ulang.');
        }

        $noNota = $notaBku->no_nota;
        $count = $this->deleteNotaWithTransaksis($notaBku);

        return redirect()->route('nota-bku.index')->with('success', 'Nota ' . $noNota . ' beserta ' . $count . ' transaksi terkait dihapus.');
    }

    /**
     * Hapus (soft) nota beserta semua transaksi terkait, lengkap dengan
     * AuditLog + Outbox per transaksi dan nota. Dipakai oleh halaman Riwayat
     * Nota (destroy) dan juga cascade dari penghapusan transaksi BKU yang
     * merupakan bagian dari nota (TransaksiBkuController::destroy/destroyAll).
     *
     * @return int jumlah transaksi terkait yang ikut dihapus
     */
    public function deleteNotaWithTransaksis(NotaBku $notaBku): int
    {
        $user = auth()->user();

        $noNota = $notaBku->no_nota;
        $transaksis = $notaBku->transaksiBkus()->get();

        foreach ($transaksis as $transaksi) {
            $transaksi->delete();
            Outbox::record('TransaksiBku', $transaksi->id, 'delete', [
                'no_bukti' => $transaksi->no_bukti,
                'jumlah' => (float) $transaksi->jumlah,
                'nota' => $noNota,
            ]);
        }

        $notaBku->delete();

        if ($user !== null) {
            AuditLog::record('nota_bku', 'delete', [
                'no_nota' => $noNota,
                'jumlah_transaksi' => $transaksis->count(),
            ], null, $user->id);
            Cache::increment('dash_ver_' . $user->id);
        }

        Outbox::record('NotaBku', $notaBku->id, 'delete', ['no_nota' => $noNota]);

        return $transaksis->count();
    }

    public function cetak(NotaBku $notaBku): Response
    {
        $notaBku->load('items.rkasItem', 'kegiatan', 'sumberDana', 'tahunAnggaran');

        $total = (float) $notaBku->items->sum('subtotal');

        $pdf = Pdf::loadView('nota-bku.cetak', compact('notaBku', 'total'))
            ->setPaper([0, 0, 609.4488, 935.433], 'portrait');

        $safeFileName = str_replace(['/', '\\'], '-', $notaBku->no_nota);

        return $pdf->stream('nota-' . $safeFileName . '.pdf');
    }
}