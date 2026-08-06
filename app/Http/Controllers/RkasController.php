<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RkasItem;
use App\Models\TahunAnggaran;
use App\Models\SumberDana;
use App\Models\MasterProgram;
use App\Models\MasterKodeRekening;
use App\Models\TransaksiBku;
use Illuminate\Http\Request;

class RkasController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $bulan = $request->get('bulan', date('n'));
        $programId = $request->get('program_id');
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

        $totalJumlah = 0;
        $totalRealisasi = 0;
        $belumLengkapCount = 0;
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

            $filteredIds = fn () => (clone $baseQuery)->select('id');

            $totalJumlah = (float) RkasItem::whereIn('id', $filteredIds())->sum('jumlah');

            $totalRealisasi = (float) TransaksiBku::joinSub($filteredIds(), 'ri_filtered', fn ($j) => $j->on('transaksi_bku.rkas_item_id', '=', 'ri_filtered.id'))
                ->where('transaksi_bku.jenis', 'pengeluaran')
                ->sum('transaksi_bku.jumlah');

            $belumLengkapCount = RkasItem::whereIn('id', $filteredIds())
                ->where(function ($q) {
                    $q->whereNull('program_id')
                      ->orWhereNull('kode_rekening_id');
                })
                ->count();

            $rkasItems = $baseQuery
                ->with([
                    'program',
                    'kodeRekening',
                    'sumberDana',
                    'bulanRencana' => function ($q) use ($bulan): void {
                        $q->where('bulan', (int) $bulan);
                    },
                    'transaksiBkus' => function ($q): void {
                        $q->where('jenis', 'pengeluaran');
                    },
                ])
                ->orderBy('no_urut')
                ->paginate(50)
                ->withQueryString();
        }

        return view('rkas.index', compact(
            'rkasItems', 'tahunAnggaranAktif', 'tahunList', 'bulan', 'programs', 'programId',
            'totalJumlah', 'totalRealisasi', 'belumLengkapCount',
            'sumberDanaList', 'sumberDanaId'
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

        $programIdRaw = $request->input('program_id');
        $programId = is_numeric($programIdRaw) ? (int) $programIdRaw : 0;
        $sumberDanaIdRaw = $request->input('sumber_dana_id');
        $sumberDanaId = is_numeric($sumberDanaIdRaw) ? (int) $sumberDanaIdRaw : 0;
        $searchRaw = $request->input('search');
        $search = is_string($searchRaw) ? $searchRaw : '';

        $query = RkasItem::query();
        if ($tahunAnggaranAktif) {
            $query->where('tahun_anggaran_id', $tahunAnggaranAktif->id);
        }
        if ($programId > 0) {
            $query->where('program_id', $programId);
        }
        if ($sumberDanaId > 0) {
            $query->where('sumber_dana_id', $sumberDanaId);
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
