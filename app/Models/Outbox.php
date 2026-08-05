<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Antrian sinkronisasi Desktop → Server (V2).
 *
 * @property string $id
 * @property string $model
 * @property string $model_id
 * @property string $aksi
 * @property array<string, mixed>|null $payload
 * @property \Illuminate\Support\Carbon|null $synced_at
 */
class Outbox extends Model
{
    use HasUuids;

    protected $table = 'outbox';

    protected $fillable = [
        'model',
        'model_id',
        'aksi',
        'payload',
        'synced_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    /**
     * Catat perubahan data untuk disinkronkan ke server (V2).
     *
     * @param array<string, mixed> $payload
     */
    public static function record(string $model, string $modelId, string $aksi, array $payload = []): void
    {
        static::create([
            'model' => $model,
            'model_id' => $modelId,
            'aksi' => $aksi,
            'payload' => $payload,
        ]);
    }
}
