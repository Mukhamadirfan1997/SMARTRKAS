<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tahun_anggaran_id')->constrained('tahun_anggaran')->onDelete('cascade');
            $table->foreignUuid('sumber_dana_id')->nullable()->constrained('sumber_dana')->onDelete('set null');
            $table->tinyInteger('bulan')->unsigned()->nullable();
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->string('status', 20);
            $table->integer('total_baris')->nullable();
            $table->integer('baris_berhasil')->nullable();
            $table->integer('baris_gagal')->nullable();
            $table->json('error_detail')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['tahun_anggaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_log');
    }
};
