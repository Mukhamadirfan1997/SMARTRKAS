<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $transaksi_bku_id
 * @property string $nomor
 * @property \Illuminate\Support\Carbon|null $dicetak_pada
 * @property string|null $file_pdf_path
 * @use HasFactory<\Database\Factories\KwitansiFactory>
 */
class Kwitansi extends Model
{
    /** @use HasFactory<\Database\Factories\KwitansiFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'kwitansi';

    protected $fillable = [
        'transaksi_bku_id',
        'nomor',
        'dicetak_pada',
        'file_pdf_path',
    ];

    protected function casts(): array
    {
        return [
            'dicetak_pada' => 'datetime',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TransaksiBku, $this> */
    public function transaksiBku(): BelongsTo
    {
        return $this->belongsTo(TransaksiBku::class);
    }
}
