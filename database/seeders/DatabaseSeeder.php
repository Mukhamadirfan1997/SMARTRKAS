<?php

namespace Database\Seeders;

use App\Models\JenisBelanja;
use App\Models\PengaturanSekolah;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::where('email', 'admin@sekolah.test')->first();
        if ($user === null) {
            User::factory()->create([
                'name' => 'Admin Sekolah',
                'email' => 'admin@sekolah.test',
                'password' => 'password',
                'is_active' => true,
            ]);
        }

        if (PengaturanSekolah::get() === null) {
            PengaturanSekolah::create([
                'npsn' => null,
                'nama' => 'SD NEGERI CONTOH',
                'alamat' => null,
                'kecamatan' => null,
                'kabupaten' => null,
                'provinsi' => null,
                'telepon' => null,
                'email' => null,
                'nama_kepsek' => null,
                'nip_kepsek' => null,
                'nama_bendahara' => null,
                'nip_bendahara' => null,
            ]);
        }

        if (TahunAnggaran::where('status', true)->count() === 0) {
            TahunAnggaran::firstOrCreate(
                ['tahun' => 2026],
                ['status' => true]
            );
        }

        $sumberDanas = [
            ['kode' => 'BOSP-REG', 'nama' => 'BOSP Reguler'],
            ['kode' => 'BOSP-KIN', 'nama' => 'BOSP Kinerja'],
        ];

        foreach ($sumberDanas as $sumberDana) {
            SumberDana::firstOrCreate(['kode' => $sumberDana['kode']], ['nama' => $sumberDana['nama']]);
        }

        $jenisBelanjas = [
            'Belanja Barang Persediaan',
            'Belanja Jasa',
            'Belanja Jasa Pemeliharaan',
            'Belanja Perjalanan Dinas',
            'Belanja Modal Peralatan & Mesin',
            'Belanja Modal Buku',
            'Belanja Modal Aset Tetap Lainnya',
            'Belanja Lainnya',
        ];

        foreach ($jenisBelanjas as $nama) {
            JenisBelanja::firstOrCreate(['nama' => $nama]);
        }
    }
}
