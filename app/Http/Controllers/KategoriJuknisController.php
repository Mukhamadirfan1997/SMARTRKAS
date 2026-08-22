<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\KategoriJuknis;
use App\Models\MasterKodeRekening;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KategoriJuknisController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $kategoriJuknis = KategoriJuknis::query()
            ->withCount('kodeRekenings')
            ->orderBy('nama')
            ->get();

        return view('pengaturan.kategori-juknis.index', compact('kategoriJuknis'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validateKategori(Request $request, ?KategoriJuknis $except = null): array
    {
        $unique = 'unique:kategori_juknis,nama';

        if ($except !== null) {
            $unique .= ',' . $except->id;
        }

        return $request->validate([
            'nama' => ['required', 'string', 'max:255', $unique],
            'arah' => ['required', 'in:maksimal,minimal'],
            'batas_persen' => ['required', 'numeric', 'min:0', 'max:100'],
            'berlaku_untuk' => ['nullable', 'string', 'max:50'],
        ]);
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $this->validateKategori($request);

        $kategori = KategoriJuknis::create($validated);

        AuditLog::record('kategori_juknis', 'create', [
            'nama' => $kategori->nama,
            'arah' => $kategori->arah,
            'batas_persen' => $kategori->batas_persen,
        ]);

        return redirect()
            ->route('pengaturan.kategori-juknis.index')
            ->with('success', "Kategori Juknis \"{$kategori->nama}\" berhasil ditambahkan.");
    }

    public function edit(KategoriJuknis $kategoriJuknis): \Illuminate\View\View
    {
        return view('pengaturan.kategori-juknis.edit', [
            'kategori' => $kategoriJuknis,
        ]);
    }

    public function update(Request $request, KategoriJuknis $kategoriJuknis): \Illuminate\Http\RedirectResponse
    {
        $validated = $this->validateKategori($request, $kategoriJuknis);

        $dataLama = $kategoriJuknis->only(['nama', 'arah', 'batas_persen', 'berlaku_untuk']);

        $kategoriJuknis->update($validated);

        AuditLog::record('kategori_juknis', 'update', $kategoriJuknis->only(['nama', 'arah', 'batas_persen', 'berlaku_untuk']), $dataLama);

        return redirect()
            ->route('pengaturan.kategori-juknis.index')
            ->with('success', "Kategori Juknis \"{$kategoriJuknis->nama}\" berhasil diupdate.");
    }

    public function destroy(KategoriJuknis $kategoriJuknis): \Illuminate\Http\RedirectResponse
    {
        $data = $kategoriJuknis->only(['nama', 'arah', 'batas_persen']);
        $jumlahRekening = $kategoriJuknis->kodeRekenings()->count();

        $kategoriJuknis->delete();

        AuditLog::record('kategori_juknis', 'delete', $data + ['jumlah_kode_rekening_terlepas' => $jumlahRekening]);

        return redirect()
            ->route('pengaturan.kategori-juknis.index')
            ->with('success', "Kategori Juknis \"{$data['nama']}\" berhasil dihapus beserta pemetaannya.");
    }

    public function pemetaan(Request $request): \Illuminate\View\View
    {
        $kategoriJuknis = KategoriJuknis::orderBy('nama')->get();

        $query = MasterKodeRekening::query()->with(['jenisBelanja', 'kategoriJuknis']);

        if ($search = trim((string) $request->get('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('kode', 'LIKE', "%{$search}%")
                    ->orWhere('nama', 'LIKE', "%{$search}%");
            });
        }

        $rekenings = $query->orderBy('kode')->paginate(50)->withQueryString();

        return view('pengaturan.kategori-juknis.pemetaan', compact('kategoriJuknis', 'rekenings'));
    }

    /**
     * Simpan pemetaan kode rekening -> kategori secara batch.
     *
     * `rows[]` berisi id SEMUA kode rekening yang tampil di halaman (hidden input)
     * supaya baris yang semua checkbox-nya dikosongkan tetap tersinkron (pemetaan
     * dilepas). Baris yang tidak ada dalam payload (mis. halaman lain dari paginasi)
     * TIDAK disentuh.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    public function simpanPemetaan(Request $request)
    {
        $validated = $request->validate([
            'rows' => ['required', 'array'],
            'map' => ['nullable', 'array'],
            'map.*' => ['array'],
            'map.*.*' => ['string', 'exists:kategori_juknis,id'],
        ]);

        $rows = $this->stringValues($validated['rows'] ?? []);
        $map = is_array($validated['map'] ?? null) ? $validated['map'] : [];

        if ($rows === []) {
            return back()->with('error', 'Tidak ada kode rekening yang diproses.');
        }

        $rekenings = MasterKodeRekening::whereIn('id', $rows)->get();
        $jumlahDiproses = 0;

        DB::transaction(function () use ($rekenings, $map, &$jumlahDiproses): void {
            foreach ($rekenings as $rekening) {
                /** @var array<int, string> $ids */
                $ids = $this->stringValues($map[$rekening->id] ?? []);

                $rekening->kategoriJuknis()->sync($ids);
                $jumlahDiproses++;
            }
        });

        AuditLog::record('kategori_juknis', 'update_pemetaan', [
            'jumlah_rekening_diperbarui' => $jumlahDiproses,
        ]);

        return redirect()
            ->route('pengaturan.kategori-juknis.pemetaan', $request->only('q', 'page'))
            ->with('success', "Pemetaan {$jumlahDiproses} kode rekening berhasil disimpan.");
    }

    /**
     * Ambil hanya nilai string dari array hasil validasi.
     *
     * @param  mixed  $value
     * @return list<string>
     */
    private function stringValues($value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, fn ($item): bool => is_string($item) && $item !== ''));
    }
}
