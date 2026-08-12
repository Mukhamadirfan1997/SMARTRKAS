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
 * @property string $no_nota
 * @property string $tanggal
 * @property int|null $bulan
 * @property string $kegiatan_id
 * @property string $sumber_dana_id
 * @property string $tahun_anggaran_id
 * @property string|null $toko_penerima
 * @property string|null $metode_pengadaan
 * @property string|null $no_invoice_siplah
 * @property string|null $uraian
 * @property int $created_by
 * @property string|null $deleted_at
 * @use HasFactory<\Database\Factories\NotaBkuFactory>
 */
class NotaBku extends Model
{
    /** @use HasFactory<\Database\Factories\NotaBkuFactory> */
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'nota_bku';

    protected $fillable = [
        'no_nota',
        'tanggal',
        'bulan',
        'kegiatan_id',
        'sumber_dana_id',
        'tahun_anggaran_id',
        'toko_penerima',
        'metode_pengadaan',
        'no_invoice_siplah',
        'uraian',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\MasterProgram, $this> */
    public function kegiatan(): BelongsTo
    {
        return $this->belongsTo(MasterProgram::class, 'kegiatan_id');
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\NotaBkuItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(NotaBkuItem::class, 'nota_bku_id')->orderBy('urutan');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\TransaksiBku, $this> */
    public function transaksiBkus(): HasMany
    {
        return $this->hasMany(TransaksiBku::class, 'nota_bku_id');
    }
}