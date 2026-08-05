<?php

namespace App\Http\Controllers;

use App\Models\TahunAnggaran;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TahunAnggaranController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $tahunAnggarans = TahunAnggaran::orderByDesc('tahun')->get();

        return view('tahun-anggaran.index', compact('tahunAnggarans'));
    }

    public function create(): \Illuminate\View\View
    {
        return view('tahun-anggaran.create');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'tahun' => 'required|integer|between:2020,2099|unique:tahun_anggaran,tahun',
        ]);

        TahunAnggaran::create($validated);

        return redirect()->route('tahun-anggaran.index')->with('success', 'Tahun anggaran berhasil ditambahkan.');
    }

    public function edit(TahunAnggaran $tahunAnggaran): \Illuminate\View\View
    {
        return view('tahun-anggaran.edit', compact('tahunAnggaran'));
    }

    public function update(Request $request, TahunAnggaran $tahunAnggaran): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'tahun' => 'required|integer|between:2020,2099|unique:tahun_anggaran,tahun,' . $tahunAnggaran->id,
        ]);

        $tahunAnggaran->update($validated);

        return redirect()->route('tahun-anggaran.index')->with('success', 'Tahun anggaran berhasil diupdate.');
    }

    public function setActive(TahunAnggaran $tahunAnggaran): \Illuminate\Http\RedirectResponse
    {
        $sebelumnya = TahunAnggaran::getActive();

        DB::transaction(function () use ($tahunAnggaran): void {
            TahunAnggaran::query()->update(['status' => false]);
            $tahunAnggaran->update(['status' => true]);
        });

        Cache::forget('tahun_anggaran_active');

        $pesan = 'Tahun anggaran ' . $tahunAnggaran->tahun . ' berhasil diaktifkan.';
        if ($sebelumnya) {
            $pesan .= ' Tahun ' . $sebelumnya->tahun . ' telah dinonaktifkan.';
        }

        return redirect()->route('tahun-anggaran.index')->with('success', $pesan);
    }

    public function destroy(TahunAnggaran $tahunAnggaran): \Illuminate\Http\RedirectResponse
    {
        if ($tahunAnggaran->status) {
            return back()->with('error', 'Tahun anggaran aktif tidak boleh dihapus. Nonaktifkan terlebih dahulu dengan mengaktifkan tahun anggaran lain.');
        }

        $tahunAnggaran->delete();
        Cache::forget('tahun_anggaran_active');

        return redirect()->route('tahun-anggaran.index')->with('success', 'Tahun anggaran berhasil dihapus.');
    }
}
