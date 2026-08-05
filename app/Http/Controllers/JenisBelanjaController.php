<?php

namespace App\Http\Controllers;

use App\Models\JenisBelanja;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class JenisBelanjaController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $jenisBelanjas = JenisBelanja::orderBy('nama')->paginate(50);

        return view('jenis-belanja.index', compact('jenisBelanjas'));
    }

    public function create(): \Illuminate\View\View
    {
        return view('jenis-belanja.create');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'nama' => 'required|unique:jenis_belanja,nama',
        ]);

        JenisBelanja::create($validated);

        Cache::forget('jenis_belanjas');

        return redirect()->route('jenis-belanja.index')->with('success', 'Jenis Belanja berhasil ditambahkan.');
    }

    public function edit(JenisBelanja $jenisBelanja): \Illuminate\View\View
    {
        return view('jenis-belanja.edit', compact('jenisBelanja'));
    }

    public function update(Request $request, JenisBelanja $jenisBelanja): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'nama' => 'required|unique:jenis_belanja,nama,' . $jenisBelanja->id,
        ]);

        $jenisBelanja->update($validated);

        Cache::forget('jenis_belanjas');

        return redirect()->route('jenis-belanja.index')->with('success', 'Jenis Belanja berhasil diupdate.');
    }

    public function destroy(JenisBelanja $jenisBelanja): \Illuminate\Http\RedirectResponse
    {
        $jenisBelanja->delete();
        Cache::forget('jenis_belanjas');

        return back()->with('success', 'Jenis Belanja berhasil dihapus.');
    }
}
