<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkas_item', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->onDelete('cascade');
            $table->integer('no_urut')->nullable();
            $table->text('uraian');
            $table->foreignUuid('program_id')->nullable()->constrained('master_program')->onDelete('set null');
            $table->foreignUuid('kode_rekening_id')->nullable()->constrained('master_kode_rekening')->onDelete('set null');
            $table->foreignUuid('sumber_dana_id')->nullable()->constrained('sumber_dana')->onDelete('set null');
            $table->decimal('volume', 15, 2)->nullable();
            $table->string('satuan', 50)->nullable();
            $table->decimal('tarif', 15, 2)->nullable();
            $table->decimal('jumlah', 15, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tahun_anggaran_id', 'no_urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkas_item');
    }
};
