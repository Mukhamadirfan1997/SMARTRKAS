<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tahun_anggaran_id
 * @property string|null $sumber_dana_id
 * @property int|null $bulan
 * @property string $file_name
 * @property string|null $file_path
 * @property string $status
 * @property int|null $total_baris
 * @property int|null $baris_berhasil
 * @property int|null $baris_gagal
 * @property array<string, mixed>|null $error_detail
 * @property int|null $uploaded_by
 * @property \Illuminate\Support\Carbon|null $finished_at
 * @use HasFactory<\Database\Factories\ImportLogFactory>
 */
class ImportLog extends Model
{
    /** @use HasFactory<\Database\Factories\ImportLogFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'import_log';

    protected $fillable = [
        'tahun_anggaran_id',
        'sumber_dana_id',
        'bulan',
        'file_name',
        'file_path',
        'status',
        'total_baris',
        'baris_berhasil',
        'baris_gagal',
        'error_detail',
        'uploaded_by',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'error_detail' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\SumberDana, $this> */
    public function sumberDana(): BelongsTo
    {
        return $this->belongsTo(SumberDana::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\TahunAnggaran, $this> */
    public function tahunAnggaran(): BelongsTo
    {
        return $this->belongsTo(TahunAnggaran::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
