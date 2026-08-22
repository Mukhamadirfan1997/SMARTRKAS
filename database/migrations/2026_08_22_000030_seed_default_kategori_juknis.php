<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Seed 3 kategori default Juknis BOSP TANPA pemetaan kode rekening apa pun
     * (kesepakatan: default tidak ditandai, tidak ditebak otomatis).
     *
     * Dibuat sebagai migrasi (bukan seeder) agar instalasi desktop existing —
     * yang hanya menjalankan `artisan migrate --force` tiap startup, bukan
     * seeder — juga mendapatkan default ini saat upgrade.
     */
    public function up(): void
    {
        $defaults = [
            ['nama' => 'Honor', 'arah' => 'maksimal', 'batas_persen' => 20],
            ['nama' => 'Pemeliharaan', 'arah' => 'maksimal', 'batas_persen' => 20],
            ['nama' => 'Buku', 'arah' => 'minimal', 'batas_persen' => 10],
        ];

        foreach ($defaults as $default) {
            $exists = DB::table('kategori_juknis')->where('nama', $default['nama'])->exists();

            if (! $exists) {
                DB::table('kategori_juknis')->insert([
                    'id' => (string) Str::uuid(),
                    'nama' => $default['nama'],
                    'arah' => $default['arah'],
                    'batas_persen' => $default['batas_persen'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Hapus hanya jika belum dipakai memetakan kode rekening apa pun,
        // supaya downgrade tidak menghapus data user yang sudah aktif.
        DB::table('kategori_juknis')
            ->whereIn('nama', ['Honor', 'Pemeliharaan', 'Buku'])
            ->whereNotExists(function ($q): void {
                $q->selectRaw(1)
                    ->from('kode_rekening_kategori_juknis as pivot')
                    ->whereColumn('pivot.kategori_juknis_id', 'kategori_juknis.id');
            })
            ->delete();
    }
};
