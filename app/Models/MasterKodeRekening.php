<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @property string|null $jenis_belanja_id
 * @use HasFactory<\Database\Factories\MasterKodeRekeningFactory>
 */
class MasterKodeRekening extends Model
{
    /** @use HasFactory<\Database\Factories\MasterKodeRekeningFactory> */
    use HasFactory, HasUuids;

    protected $table = 'master_kode_rekening';

    protected $fillable = [
        'kode',
        'nama',
        'jenis_belanja_id',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\JenisBelanja, $this> */
    public function jenisBelanja(): BelongsTo
    {
        return $this->belongsTo(JenisBelanja::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RkasItem, $this> */
    public function rkasItems(): HasMany
    {
        return $this->hasMany(RkasItem::class, 'kode_rekening_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\KategoriJuknis, $this> */
    public function kategoriJuknis(): BelongsToMany
    {
        return $this->belongsToMany(
            KategoriJuknis::class,
            'kode_rekening_kategori_juknis',
            'kode_rekening_id',
            'kategori_juknis_id',
        );
    }
}
