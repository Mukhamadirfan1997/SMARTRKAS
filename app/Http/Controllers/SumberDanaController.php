<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SumberDana;
use Illuminate\Http\Request;

class SumberDanaController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        $sumberDanas = SumberDana::orderBy('kode')->paginate(50);

        return view('sumber-dana.index', compact('sumberDanas'));
    }

    public function create(): \Illuminate\View\View
    {
        return view('sumber-dana.create');
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'kode' => 'required|unique:sumber_dana,kode',
            'nama' => 'required',
        ]);

        $sumberDana = SumberDana::create($validated);

        AuditLog::record('sumber_dana', 'create', [
            'kode' => $sumberDana->kode,
            'nama' => $sumberDana->nama,
        ]);

        return redirect()->route('sumber-dana.index')->with('success', 'Sumber Dana berhasil ditambahkan.');
    }

    public function edit(SumberDana $sumberDana): \Illuminate\View\View
    {
        return view('sumber-dana.edit', compact('sumberDana'));
    }

    public function update(Request $request, SumberDana $sumberDana): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'kode' => 'required|unique:sumber_dana,kode,' . $sumberDana->id,
            'nama' => 'required',
        ]);

        $dataLama = $sumberDana->only(['kode', 'nama']);

        $sumberDana->update($validated);

        AuditLog::record('sumber_dana', 'update', $sumberDana->only(['kode', 'nama']), $dataLama);

        return redirect()->route('sumber-dana.index')->with('success', 'Sumber Dana berhasil diupdate.');
    }

    public function destroy(SumberDana $sumberDana): \Illuminate\Http\RedirectResponse
    {
        $data = $sumberDana->only(['kode', 'nama']);

        $sumberDana->delete();

        AuditLog::record('sumber_dana', 'delete', $data);

        return back()->with('success', 'Sumber Dana berhasil dihapus.');
    }
}
