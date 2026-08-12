<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_bku', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_nota', 100)->unique();
            $table->date('tanggal');
            $table->tinyInteger('bulan')->unsigned()->nullable();
            $table->foreignUuid('kegiatan_id')->constrained('master_program')->onDelete('restrict');
            $table->foreignUuid('sumber_dana_id')->constrained('sumber_dana')->onDelete('restrict');
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->onDelete('restrict');
            $table->string('toko_penerima', 255)->nullable();
            $table->enum('metode_pengadaan', ['siplah', 'non_siplah'])->nullable();
            $table->string('no_invoice_siplah', 255)->nullable();
            $table->text('uraian')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_anggaran_id', 'bulan']);
            $table->index(['kegiatan_id', 'bulan']);
            $table->index('tanggal');
        });

        Schema::create('nota_bku_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('nota_bku_id')->constrained('nota_bku')->onDelete('cascade');
            $table->foreignUuid('rkas_item_id')->nullable()->constrained('rkas_item')->onDelete('set null');
            $table->integer('urutan')->default(1);
            $table->decimal('jumlah', 15, 2);
            $table->string('satuan', 50)->nullable();
            $table->decimal('harga_satuan', 15, 2);
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();

            $table->index('nota_bku_id');
            $table->unique(['nota_bku_id', 'rkas_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_bku_item');
        Schema::dropIfExists('nota_bku');
    }
};