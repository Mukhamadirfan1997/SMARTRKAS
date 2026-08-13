<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const KODE_REKENING = '5.1.02.01.01.0026';
    private const NAMA_JENIS = 'Belanja Cetak';

    /**
     * Seragamkan mapping jenis belanja kode rekening Bahan Cetak & Penggandaan
     * menjadi "Belanja Cetak" di semua instalasi (patokan laporan).
     * Nama rekening TIDAK diubah; hanya jenis_belanja_id.
     */
    public function up(): void
    {
        $jenisId = DB::table('jenis_belanja')->where('nama', self::NAMA_JENIS)->value('id');

        if ($jenisId === null) {
            $jenisId = Str::uuid()->toString();
            DB::table('jenis_belanja')->insert([
                'id' => $jenisId,
                'nama' => self::NAMA_JENIS,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('master_kode_rekening')
            ->where('kode', self::KODE_REKENING)
            ->update(['jenis_belanja_id' => $jenisId]);

        Cache::forget('master_kode_rekenings');
        Cache::forget('jenis_belanjas');
    }

    public function down(): void
    {
        // Data migration: nilai jenis_belanja_id sebelumnya bergantung per instalasi, tidak bisa di-revert otomatis.
    }
};
