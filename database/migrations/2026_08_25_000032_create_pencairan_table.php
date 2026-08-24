<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modul Data Pencairan: pencatatan penerimaan SP2D/pencairan dari rekening
     * koran bank (di luar BKU kas). BKU hanya mencatat mutasi kas tunai
     * (tarik tunai, kategori_arus = 'mutasi'); uang yang masuk ke rekening
     * sekolah dicatat di sini dan menjadi sumber kolom D (Total Penerimaan)
     * pada Formulir BOS-K7b/K7c.
     */
    public function up(): void
    {
        Schema::create('pencairan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tahun_anggaran_id');
            $table->uuid('sumber_dana_id')->nullable();
            $table->date('tanggal');
            $table->unsignedTinyInteger('bulan');
            $table->decimal('nominal', 15, 2);
            $table->string('keterangan')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreign('tahun_anggaran_id')->references('id')->on('tahun_anggaran')->restrictOnDelete();
            $table->foreign('sumber_dana_id')->references('id')->on('sumber_dana')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_anggaran_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pencairan');
    }
};
