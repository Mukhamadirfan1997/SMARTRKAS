<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->foreignUuid('nota_bku_id')->nullable()->constrained('nota_bku')->onDelete('set null');
            $table->index(['nota_bku_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_bku', function (Blueprint $table) {
            $table->dropIndex(['nota_bku_id', 'bulan']);
            $table->dropConstrainedForeignId('nota_bku_id');
        });
    }
};