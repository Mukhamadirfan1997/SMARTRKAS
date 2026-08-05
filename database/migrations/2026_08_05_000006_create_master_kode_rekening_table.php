<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_kode_rekening', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 100)->unique();
            $table->string('nama');
            $table->foreignUuid('jenis_belanja_id')->nullable()->constrained('jenis_belanja')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_kode_rekening');
    }
};
