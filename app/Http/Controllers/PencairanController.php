<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pencairan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Support\NumberParser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PencairanController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $tahunAnggarans = TahunAnggaran::orderByDesc('tahun')->get();
        $tahunAnggaran = $this->resolveTahun($request, $tahunAnggarans);
        $sumberDanas = SumberDana::orderBy('nama')->get();

        $query = Pencairan::query()
            ->with(['sumberDana', 'createdBy'])
            ->where('tahun_anggaran_id', $tahunAnggaran?->id)
            ->when($request->filled('sumber_dana_id'), fn ($q) => $q->where('sumber_dana_id', $request->string('sumber_dana_id')));

        $totalTahun = (float) (clone $query)->sum('nominal');

        $pencairans = $query->orderByDesc('tanggal')
            ->paginate(50)->withQueryString();

        return view('pencairan.index', [
            'pencairans' => $pencairans,
            'tahunAnggarans' => $tahunAnggarans,
            'tahunAnggaran' => $tahunAnggaran,
            'sumberDanas' => $sumberDanas,
            'totalTahun' => $totalTahun,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateData($request);

        $tahunAnggaran = TahunAnggaran::getActive();

        if ($tahunAnggaran === null) {
            return back()->with('error', 'Tidak ada tahun anggaran aktif. Atur tahun anggaran terlebih dahulu.');
        }

        $pencairan = Pencairan::create($validated + [
            'tahun_anggaran_id' => $tahunAnggaran->id,
            'created_by' => auth()->id(),
        ]);

        AuditLog::record('pencairan', 'create', [
            'tanggal' => $pencairan->tanggal->toDateString(),
            'nominal' => $pencairan->nominal,
            'sumber_dana_id' => $pencairan->sumber_dana_id,
            'keterangan' => $pencairan->keterangan,
        ]);

        return redirect()
            ->route('pencairan.index', array_filter([
                'tahun' => $request->query('tahun'),
                'sumber_dana_id' => $request->query('sumber_dana_id'),
            ]))
            ->with('success', 'Pencairan sebesar Rp '.number_format((float) $pencairan->nominal, 0, ',', '.').' berhasil dicatat.');
    }

    public function edit(Pencairan $pencairan): \Illuminate\View\View
    {
        $sumberDanas = SumberDana::orderBy('nama')->get();

        return view('pencairan.edit', [
            'pencairan' => $pencairan,
            'sumberDanas' => $sumberDanas,
        ]);
    }

    public function update(Request $request, Pencairan $pencairan): RedirectResponse
    {
        $validated = $this->validateData($request);

        // Tahun anggaran pencairan tidak diubah saat edit (mengikuti baris lama).
        $dataLama = $pencairan->only(['tanggal', 'bulan', 'nominal', 'sumber_dana_id', 'keterangan']);

        $pencairan->update($validated);

        AuditLog::record('pencairan', 'update', $pencairan->only(['tanggal', 'bulan', 'nominal', 'sumber_dana_id', 'keterangan']), $dataLama);

        return redirect()
            ->route('pencairan.index')
            ->with('success', 'Data pencairan berhasil diperbarui.');
    }

    public function destroy(Pencairan $pencairan): RedirectResponse
    {
        $data = $pencairan->only(['tanggal', 'nominal', 'keterangan']);

        $pencairan->delete();

        AuditLog::record('pencairan', 'delete', $data);

        return redirect()
            ->route('pencairan.index')
            ->with('success', 'Data pencairan berhasil dihapus.');
    }

    /**
     * Normalisasi nominal (format Indonesia) SEBELUM validasi, lalu validasi
     * field bersama untuk store & update. bulan diturunkan dari tanggal.
     *
     * @return array{sumber_dana_id: string, tanggal: string, bulan: int, nominal: string, keterangan: string|null}
     */
    private function validateData(Request $request): array
    {
        $request->merge([
            'nominal' => NumberParser::rupiah($request->input('nominal')),
        ]);

        $validated = $request->validate([
            'tanggal' => ['required', 'date'],
            'sumber_dana_id' => ['required', 'string', 'exists:sumber_dana,id'],
            'nominal' => ['required', 'numeric', 'min:0.01'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);

        return [
            'sumber_dana_id' => $validated['sumber_dana_id'],
            'tanggal' => $validated['tanggal'],
            'bulan' => (int) Carbon::parse($validated['tanggal'])->format('n'),
            'nominal' => $validated['nominal'],
            'keterangan' => filled($validated['keterangan'] ?? null) ? trim((string) $validated['keterangan']) : null,
        ];
    }

    /**
     * @param  Collection<int, TahunAnggaran>  $tahunAnggarans
     */
    private function resolveTahun(Request $request, Collection $tahunAnggarans): ?TahunAnggaran
    {
        if ($request->filled('tahun')) {
            /** @var TahunAnggaran|null */
            $found = $tahunAnggarans->firstWhere('id', (string) $request->input('tahun'));

            return $found;
        }

        return TahunAnggaran::getActive();
    }
}
