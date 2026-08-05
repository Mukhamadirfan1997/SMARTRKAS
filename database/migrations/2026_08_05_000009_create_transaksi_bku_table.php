<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi_bku', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rkas_item_id')->nullable()->constrained('rkas_item')->onDelete('set null');
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->onDelete('restrict');
            $table->foreignUuid('sumber_dana_id')->nullable()->constrained('sumber_dana')->onDelete('restrict');
            $table->date('tanggal');
            $table->tinyInteger('bulan')->unsigned()->nullable();
            $table->string('no_bukti', 100)->unique();
            $table->enum('jenis', ['penerimaan', 'pengeluaran']);
            $table->decimal('jumlah', 15, 2);
            $table->decimal('volume', 15, 2)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->string('toko_penerima', 255)->nullable();
            $table->enum('metode_pengadaan', ['siplah', 'non_siplah'])->nullable();
            $table->text('uraian')->nullable();
            $table->integer('tahap')->default(1);
            $table->boolean('status_lunas')->default(true);
            $table->decimal('saldo_berjalan', 15, 2)->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_anggaran_id', 'bulan']);
            $table->index(['sumber_dana_id', 'bulan']);
            $table->index('tanggal');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi_bku');
    }
};
