<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $no_revisi
 * @property string $jenis
 * @property string $tanggal
 * @property string $tahun_anggaran_id
 * @property string $sumber_dana_id
 * @property string|null $keterangan
 * @property float $sebelum_total
 * @property float $sesudah_total
 * @property array<string, mixed>|null $data_perubahan
 * @property int $created_by
 * @property string|null $deleted_at
 * @use HasFactory<\Database\Factories\RkasRevisiFactory>
 */
class RkasRevisi extends Model
{
    /** @use HasFactory<\Database\Factories\RkasRevisiFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'rkas_revisi';

    protected $fillable = [
        'no_revisi',
        'jenis',
        'tanggal',
        'tahun_anggaran_id',
        'sumber_dana_id',
        'keterangan',
        'sebelum_total',
        'sesudah_total',
        'data_perubahan',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'data_perubahan' => 'array',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RkasRevisiItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(RkasRevisiItem::class, 'rkas_revisi_id')->orderBy('urutan');
    }
}
