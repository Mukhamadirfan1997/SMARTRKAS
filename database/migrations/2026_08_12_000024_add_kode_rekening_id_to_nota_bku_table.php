<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nota_bku', function (Blueprint $table) {
            $table->foreignUuid('kode_rekening_id')
                ->nullable()
                ->after('kegiatan_id')
                ->constrained('master_kode_rekening')
                ->onDelete('set null');

            $table->index(['kode_rekening_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::table('nota_bku', function (Blueprint $table) {
            $table->dropIndex(['kode_rekening_id', 'bulan']);
            $table->dropConstrainedForeignId('kode_rekening_id');
        });
    }
};