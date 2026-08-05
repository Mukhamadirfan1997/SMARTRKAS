<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('model', 100);
            $table->uuid('model_id');
            $table->string('aksi', 20);
            $table->json('payload')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index(['synced_at', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox');
    }
};
