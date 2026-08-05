<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_program', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('kode', 100);
            $table->string('nama');
            $table->string('program')->nullable();
            $table->string('sub_program')->nullable();
            $table->foreignUuid('parent_id')->nullable()->constrained('master_program')->onDelete('cascade');
            $table->integer('level')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_program');
    }
};
