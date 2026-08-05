<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int|null $user_id
 * @property string $tabel
 * @property string $aksi
 * @property array<string, mixed>|null $data_lama
 * @property array<string, mixed>|null $data_baru
 */
class AuditLog extends Model
{
    use HasUuids;

    protected $table = 'audit_log';

    protected $fillable = [
        'user_id',
        'tabel',
        'aksi',
        'data_lama',
        'data_baru',
    ];

    protected function casts(): array
    {
        return [
            'data_lama' => 'array',
            'data_baru' => 'array',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
