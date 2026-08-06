<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
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

        $jenisBelanja = JenisBelanja::create($validated);

        AuditLog::record('jenis_belanja', 'create', ['nama' => $jenisBelanja->nama]);

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

        $dataLama = $jenisBelanja->only(['nama']);

        $jenisBelanja->update($validated);

        AuditLog::record('jenis_belanja', 'update', $jenisBelanja->only(['nama']), $dataLama);

        Cache::forget('jenis_belanjas');

        return redirect()->route('jenis-belanja.index')->with('success', 'Jenis Belanja berhasil diupdate.');
    }

    public function destroy(JenisBelanja $jenisBelanja): \Illuminate\Http\RedirectResponse
    {
        $data = $jenisBelanja->only(['nama']);

        $jenisBelanja->delete();

        AuditLog::record('jenis_belanja', 'delete', $data);

        Cache::forget('jenis_belanjas');

        return back()->with('success', 'Jenis Belanja berhasil dihapus.');
    }
}
