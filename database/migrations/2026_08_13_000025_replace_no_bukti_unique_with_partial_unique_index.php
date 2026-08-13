<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->dropUnique(['no_bukti']);
        });

        if (DB::connection()->getDriverName() === 'sqlite') {
            // Partial unique index: hanya baris aktif (belum di-soft-delete) yang
            // harus unik. Nomor bukti pada baris soft-deleted boleh dipakai ulang.
            DB::statement('CREATE UNIQUE INDEX transaksi_bku_no_bukti_aktif_unique ON transaksi_bku (no_bukti) WHERE deleted_at IS NULL');
        } else {
            Schema::table('transaksi_bku', function (Blueprint $table) {
                $table->unique(['no_bukti', 'deleted_at'], 'transaksi_bku_no_bukti_aktif_unique');
            });
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('DROP INDEX IF EXISTS transaksi_bku_no_bukti_aktif_unique');
        } else {
            Schema::table('transaksi_bku', function (Blueprint $table) {
                $table->dropUnique('transaksi_bku_no_bukti_aktif_unique');
            });
        }

        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->unique('no_bukti', 'transaksi_bku_no_bukti_unique');
        });
    }
};
