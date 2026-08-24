<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Penerimaan SP2D / pencairan dari rekening koran bank (di luar BKU kas).
 * BKU kas hanya mencatat tarik tunai (mutasi); uang yang masuk ke rekening
 * sekolah dicatat di sini dan menjadi sumber kolom D pada Formulir K7b/K7c.
 *
 * @property string $id
 * @property string $tahun_anggaran_id
 * @property string|null $sumber_dana_id
 * @property \Illuminate\Support\Carbon $tanggal
 * @property int $bulan
 * @property float $nominal
 * @property string|null $keterangan
 * @property string|null $created_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class Pencairan extends Model
{
    /** @use HasFactory<\Database\Factories\PencairanFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    protected $table = 'pencairan';

    protected $fillable = [
        'tahun_anggaran_id',
        'sumber_dana_id',
        'tanggal',
        'bulan',
        'nominal',
        'keterangan',
        'created_by',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'bulan' => 'integer',
        'nominal' => 'float',
    ];

    /** @return BelongsTo<\App\Models\TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return BelongsTo<\App\Models\SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class);
    }

    /** @return BelongsTo<\App\Models\User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
