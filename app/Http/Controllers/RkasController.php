<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\JenisBelanja;
use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use App\Models\TahunAnggaran;
use App\Models\SumberDana;
use App\Models\MasterProgram;
use App\Models\MasterKodeRekening;
use App\Support\NumberParser;
use App\Support\RealisasiQuery;
use Illuminate\Http\Request;

class RkasController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $bulanInput = $request->input('bulan');
        $bulan = $request->filled('bulan') && is_numeric($bulanInput) ? (int) $bulanInput : null;
        $programId = $request->get('program_id');
        $kodeRekeningId = $request->get('kode_rekening_id');
        $jenisBelanjaId = $request->get('jenis_belanja_id');
        $tahunAnggaranAktif = TahunAnggaran::getActive();
        $tahunInput = $request->get('tahun');
        if ($tahunInput) {
            $tahunRecord = TahunAnggaran::where('tahun', (int) $tahunInput)->first();
            if ($tahunRecord) {
                $tahunAnggaranAktif = $tahunRecord;
            }
        }
        $tahunList = TahunAnggaran::orderBy('tahun', 'desc')->get();
        $sumberDanaList = SumberDana::orderBy('kode')->get();
        $sumberDanaId = request('sumber_dana_id');
        $programs = MasterProgram::whereNull('parent_id')->orderBy('kode')->get();
        $kodeRekenings = MasterKodeRekening::orderBy('kode')->get();
        $jenisBelanjas = JenisBelanja::orderBy('nama')->get();

        $totalJumlah = 0;
        $totalRealisasi = 0;
        $belumLengkapCount = 0;
        $persentaseCapaian = 0;
        $jenisBelanjaRealisasi = collect();
        $filteredIdsNoBulan = collect();
        $rkasItems = collect();

        if ($tahunAnggaranAktif) {
            $baseQuery = $tahunAnggaranAktif->rkasItems()->getQuery();

            if ($programId) {
                $baseQuery->where('program_id', $programId);
            }

            if ($search = $request->get('search')) {
                $baseQuery->where('uraian', 'LIKE', "%{$search}%");
            }

            if ($sumberDanaId) {
                $baseQuery->where('sumber_dana_id', $sumberDanaId);
            }

            if ($kodeRekeningId) {
                $baseQuery->where('kode_rekening_id', $kodeRekeningId);
            }

            if ($jenisBelanjaId) {
                $baseQuery->whereHas('kodeRekening', function ($q) use ($jenisBelanjaId): void {
                    $q->where('jenis_belanja_id', $jenisBelanjaId);
                });
            }

            $filteredIds = fn () => (clone $baseQuery)->select('id');

            $filteredIdsNoBulan = (clone $baseQuery)->pluck('id');

            if ($bulan) {
                $baseQuery->whereHas('bulanRencana', function ($q) use ($bulan): void {
                    $q->where('bulan', $bulan);
                });
            }

            if ($bulan) {
                $totalJumlah = (float) RkasItemBulan::whereIn('rkas_item_id', $filteredIds())
                    ->where('bulan', $bulan)
                    ->sum('rencana');
                $totalRealisasi = (float) RealisasiQuery::base()
                    ->whereIn('rb.rkas_item_id', $filteredIds())
                    ->where('rb.bulan', $bulan)
                    ->sum('rb.jumlah');
            } else {
                $totalJumlah = (float) RkasItem::whereIn('id', $filteredIds())->sum('jumlah');

                $totalRealisasi = (float) RealisasiQuery::base()
                    ->joinSub($filteredIds()->toBase(), 'ri_filtered', fn ($j) => $j->on('rb.rkas_item_id', '=', 'ri_filtered.id'))
                    ->sum('rb.jumlah');
            }

            $persentaseCapaian = $totalJumlah > 0 ? round(($totalRealisasi / $totalJumlah) * 100, 1) : 0;

            if ($filteredIdsNoBulan->isNotEmpty()) {
                $chartData = RealisasiQuery::base()
                    ->whereIn('rb.rkas_item_id', $filteredIdsNoBulan)
                    ->join('rkas_item', 'rkas_item.id', '=', 'rb.rkas_item_id')
                    ->join('master_kode_rekening', 'rkas_item.kode_rekening_id', '=', 'master_kode_rekening.id')
                    ->join('jenis_belanja', 'master_kode_rekening.jenis_belanja_id', '=', 'jenis_belanja.id')
                    ->selectRaw('jenis_belanja.nama as label, sum(rb.jumlah) as total')
                    ->groupBy('jenis_belanja.nama')
                    ->orderByDesc('total')
                    ->get();

                $jenisBelanjaRealisasi = $chartData
                    ->filter(fn ($d) => (float) ($d->total ?? 0) > 0)
                    ->map(fn ($d): array => [
                        'label' => (string) ($d->label ?? 'Tidak Terkategori'),
                        'total' => (float) ($d->total ?? 0),
                        'persen' => $totalRealisasi > 0 ? round(((float) $d->total / $totalRealisasi) * 100, 1) : 0,
                    ])
                    ->values();
            }

            $belumLengkapCount = RkasItem::whereIn('id', $filteredIds())
                ->where(function ($q) {
                    $q->whereNull('program_id')
                      ->orWhereNull('kode_rekening_id');
                })
                ->count();

            $rkasItems = $baseQuery
                ->with([
                    'program',
                    'kodeRekening.jenisBelanja',
                    'sumberDana',
                    'bulanRencana' => function ($q) use ($bulan): void {
                        if ($bulan) {
                            $q->where('bulan', $bulan);
                        }
                    },
                    'transaksiBkus' => function ($q) use ($bulan): void {
                        $q->where('jenis', 'pengeluaran');
                        if ($bulan) {
                            $q->where('bulan', $bulan);
                        }
                    },
                    'notaBkuItems' => function ($q) use ($bulan): void {
                        $q->whereHas('notaBku', function ($q2) use ($bulan): void {
                            $q2->whereNull('deleted_at');
                            if ($bulan) {
                                $q2->where('bulan', $bulan);
                            }
                        });
                    },
                ])
                ->orderBy('no_urut')
                ->paginate(50)
                ->withQueryString();
        }

        return view('rkas.index', compact(
            'rkasItems', 'tahunAnggaranAktif', 'tahunList', 'bulan', 'programs', 'programId',
            'totalJumlah', 'totalRealisasi', 'belumLengkapCount',
            'persentaseCapaian', 'jenisBelanjaRealisasi',
            'sumberDanaList', 'sumberDanaId',
            'kodeRekenings', 'jenisBelanjas', 'kodeRekeningId', 'jenisBelanjaId'
        ));
    }

    public function edit(RkasItem $rkasItem): \Illuminate\View\View
    {
        $masterPrograms = MasterProgram::orderBy('kode')->get();
        $masterKodeRekenings = MasterKodeRekening::orderBy('kode')->get();
        $sumberDanas = SumberDana::orderBy('kode')->get();

        return view('rkas.edit', compact('rkasItem', 'masterPrograms', 'masterKodeRekenings', 'sumberDanas'));
    }

    public function update(Request $request, RkasItem $rkasItem): \Illuminate\Http\RedirectResponse
    {
        $request->merge([
            'jumlah' => NumberParser::rupiah($request->input('jumlah')),
            'tarif' => NumberParser::rupiah($request->input('tarif')),
            'volume' => NumberParser::decimal($request->input('volume')),
        ]);

        $validated = $request->validate([
            'no_urut' => 'required|integer',
            'uraian' => 'required|string',
            'program_id' => 'nullable|exists:master_program,id',
            'kode_rekening_id' => 'nullable|exists:master_kode_rekening,id',
            'sumber_dana_id' => 'nullable|exists:sumber_dana,id',
            'volume' => 'nullable|numeric',
            'satuan' => 'nullable|string|max:255',
            'tarif' => 'nullable|numeric',
            'jumlah' => 'required|numeric',
        ]);

        $dataLama = $rkasItem->only([
            'no_urut', 'uraian', 'program_id', 'kode_rekening_id', 'sumber_dana_id',
            'volume', 'satuan', 'tarif', 'jumlah',
        ]);

        $rkasItem->update($validated);

        AuditLog::record('rkas_item', 'update', $rkasItem->only([
            'no_urut', 'uraian', 'program_id', 'kode_rekening_id', 'sumber_dana_id',
            'volume', 'satuan', 'tarif', 'jumlah',
        ]), $dataLama);

        return redirect()->route('rkas.index')->with('success', 'Item RKAS berhasil diupdate.');
    }

    public function destroy(RkasItem $rkasItem): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            abort(403);
        }

        $noUrut = $rkasItem->no_urut;
        $uraian = $rkasItem->uraian;
        $tahunId = $rkasItem->tahun_anggaran_id;

        $rkasItem->forceDelete();

        AuditLog::record('rkas_item', 'delete', [
            'no_urut' => $noUrut,
            'uraian' => $uraian,
        ], null, $user->id);

        RkasItem::renumber($tahunId);
        RkasItem::syncJumlah($tahunId);

        return redirect()->route('rkas.index')->with('success', 'Item RKAS dihapus.');
    }

    public function destroyAll(Request $request): \Illuminate\Http\RedirectResponse
    {
        $user = auth()->user();
        if ($user === null) {
            return back()->with('error', 'Sesi berakhir. Silakan login ulang.');
        }

        $tahunAnggaranAktif = TahunAnggaran::getActive();

        $tahunInput = $request->input('tahun');
        if ($tahunInput) {
            $tahunRecord = TahunAnggaran::where('tahun', (int) $tahunInput)->first();
            if ($tahunRecord) {
                $tahunAnggaranAktif = $tahunRecord;
            }
        }

        $programId = $request->input('program_id');
        $sumberDanaId = $request->input('sumber_dana_id');
        $kodeRekeningId = $request->input('kode_rekening_id');
        $jenisBelanjaId = $request->input('jenis_belanja_id');
        $searchRaw = $request->input('search');
        $search = is_string($searchRaw) ? $searchRaw : '';

        $query = RkasItem::query();
        if ($tahunAnggaranAktif) {
            $query->where('tahun_anggaran_id', $tahunAnggaranAktif->id);
        }
        if (! empty($programId)) {
            $query->where('program_id', $programId);
        }
        if (! empty($sumberDanaId)) {
            $query->where('sumber_dana_id', $sumberDanaId);
        }
        if (! empty($kodeRekeningId)) {
            $query->where('kode_rekening_id', $kodeRekeningId);
        }
        if (! empty($jenisBelanjaId)) {
            $query->whereHas('kodeRekening', fn ($q) => $q->where('jenis_belanja_id', $jenisBelanjaId));
        }
        if ($search !== '') {
            $query->where('uraian', 'LIKE', "%{$search}%");
        }

        $items = $query->get(['id', 'no_urut', 'uraian', 'tahun_anggaran_id']);
        $count = $items->count();

        if ($count === 0) {
            return back()->with('error', 'Tidak ada item RKAS yang cocok untuk dihapus.');
        }

        $uraians = [];
        $tahunIds = [];
        foreach ($items as $item) {
            $uraians[] = $item->uraian;
            $tahunIds[] = $item->tahun_anggaran_id;
            $item->forceDelete();
        }

        AuditLog::record('rkas_item', 'delete_bulk', [
            'jumlah_item' => $count,
            'uraian' => array_slice($uraians, 0, 50),
        ], null, $user->id);

        foreach (array_unique($tahunIds) as $tahunId) {
            RkasItem::renumber($tahunId);
            RkasItem::syncJumlah($tahunId);
        }

        return back()->with('success', $count . ' item RKAS dihapus.');
    }
}
