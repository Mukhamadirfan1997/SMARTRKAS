<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kwitansi', function (Blueprint $table) {
            $table->index('transaksi_bku_id', 'kwitansi_transaksi_bku_idx');
        });

        Schema::table('import_log', function (Blueprint $table) {
            $table->index(['tahun_anggaran_id', 'bulan'], 'import_log_tahun_bulan_idx');
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->index('created_at', 'audit_log_created_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('kwitansi', function (Blueprint $table) {
            $table->dropIndex('kwitansi_transaksi_bku_idx');
        });

        Schema::table('import_log', function (Blueprint $table) {
            $table->dropIndex('import_log_tahun_bulan_idx');
        });

        Schema::table('audit_log', function (Blueprint $table) {
            $table->dropIndex('audit_log_created_at_idx');
        });
    }
};
