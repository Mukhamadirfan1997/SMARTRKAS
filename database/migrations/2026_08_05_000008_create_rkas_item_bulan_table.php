<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rkas_item_bulan', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('rkas_item_id')->constrained('rkas_item')->onDelete('cascade');
            $table->tinyInteger('bulan')->unsigned();
            $table->decimal('rencana', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['rkas_item_id', 'bulan']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rkas_item_bulan');
    }
};
