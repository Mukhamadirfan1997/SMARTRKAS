<?php

namespace App\Http\Controllers;

use App\Models\PengaturanSekolah;
use Illuminate\Http\Request;

class PengaturanSekolahController extends Controller
{
    public function edit(): \Illuminate\View\View
    {
        $pengaturanSekolah = PengaturanSekolah::get() ?? new PengaturanSekolah;

        return view('pengaturan-sekolah.edit', compact('pengaturanSekolah'));
    }

    public function update(Request $request): \Illuminate\Http\RedirectResponse
    {
        $validated = $request->validate([
            'npsn' => 'nullable|string|max:20',
            'nama' => 'required|string|max:255',
            'alamat' => 'nullable|string|max:255',
            'kecamatan' => 'nullable|string|max:100',
            'kabupaten' => 'nullable|string|max:100',
            'provinsi' => 'nullable|string|max:100',
            'telepon' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:100',
            'nama_kepsek' => 'nullable|string|max:150',
            'nip_kepsek' => 'nullable|string|max:30',
            'nama_bendahara' => 'nullable|string|max:150',
            'nip_bendahara' => 'nullable|string|max:30',
        ]);

        $pengaturanSekolah = PengaturanSekolah::get();

        if ($pengaturanSekolah) {
            $pengaturanSekolah->update($validated);
        } else {
            PengaturanSekolah::create($validated);
        }

        return back()->with('success', 'Pengaturan Sekolah berhasil disimpan.');
    }
}
