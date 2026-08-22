<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kategori_juknis', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nama')->unique();
            // maksimal = batas atas proporsi belanja (mis. Honor <= x% BOSP)
            // minimal  = batas bawah proporsi belanja (mis. Buku >= y% BOSP)
            $table->enum('arah', ['maksimal', 'minimal']);
            $table->decimal('batas_persen', 5, 2);
            $table->string('berlaku_untuk', 50)->nullable();
            $table->timestamps();

            $table->index('arah');
        });

        Schema::create('kode_rekening_kategori_juknis', function (Blueprint $table) {
            $table->foreignUuid('kode_rekening_id')->constrained('master_kode_rekening')->cascadeOnDelete();
            $table->foreignUuid('kategori_juknis_id')->constrained('kategori_juknis')->cascadeOnDelete();

            // Nama eksplisit: nama default melebihi batas 64 karakter identifier MySQL.
            $table->unique(['kode_rekening_id', 'kategori_juknis_id'], 'krek_kategori_juknis_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kode_rekening_kategori_juknis');
        Schema::dropIfExists('kategori_juknis');
    }
};
