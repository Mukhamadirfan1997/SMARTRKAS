<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_template', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama_template');
            $table->foreignUuid('kode_rekening_id')->constrained('master_kode_rekening')->onDelete('cascade');
            $table->foreignUuid('kegiatan_id')->constrained('master_program')->onDelete('cascade');
            $table->string('uraian_item_snapshot');
            $table->string('toko_penerima')->nullable();
            $table->string('metode_pengadaan')->nullable();
            $table->string('uraian_dasar')->nullable();
            $table->foreignUuid('sumber_dana_id')->nullable()->constrained('sumber_dana')->onDelete('set null');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_template');
    }
};
