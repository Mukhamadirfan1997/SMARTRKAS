<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->index(['rkas_item_id', 'jenis', 'bulan'], 'transaksi_bku_item_jenis_bulan_idx');
            $table->index(['jenis', 'bulan'], 'transaksi_bku_jenis_bulan_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->dropIndex('transaksi_bku_item_jenis_bulan_idx');
            $table->dropIndex('transaksi_bku_jenis_bulan_idx');
        });
    }
};
