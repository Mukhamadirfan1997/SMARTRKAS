<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkas_revisi', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('no_revisi', 100)->unique();
            $table->enum('jenis', ['pergeseran', 'pak']);
            $table->date('tanggal');
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->onDelete('restrict');
            $table->foreignUuid('sumber_dana_id')->constrained('sumber_dana')->onDelete('restrict');
            $table->text('keterangan')->nullable();
            $table->decimal('sebelum_total', 15, 2);
            $table->decimal('sesudah_total', 15, 2);
            $table->json('data_perubahan')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_anggaran_id', 'jenis']);
            $table->index(['sumber_dana_id', 'jenis']);
            $table->index('tanggal');
        });

        Schema::create('rkas_revisi_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rkas_revisi_id')->constrained('rkas_revisi')->onDelete('cascade');
            $table->foreignUuid('rkas_item_id')->nullable()->constrained('rkas_item')->onDelete('set null');
            $table->tinyInteger('bulan')->unsigned();
            $table->enum('arah', ['naik', 'turun']);
            $table->decimal('sebelum', 15, 2);
            $table->decimal('sesudah', 15, 2);
            $table->decimal('delta', 15, 2);
            $table->integer('urutan')->default(1);
            $table->timestamps();

            $table->index('rkas_revisi_id');
            $table->index(['rkas_revisi_id', 'bulan']);
            $table->index('rkas_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkas_revisi_item');
        Schema::dropIfExists('rkas_revisi');
    }
};
