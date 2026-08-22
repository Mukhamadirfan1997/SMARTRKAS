<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property string $id
 * @property string $nama
 * @property string $arah
 * @property float $batas_persen
 * @property string|null $berlaku_untuk
 * @use HasFactory<\Database\Factories\KategoriJuknisFactory>
 */
class KategoriJuknis extends Model
{
    /** @use HasFactory<\Database\Factories\KategoriJuknisFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'nama',
        'arah',
        'batas_persen',
        'berlaku_untuk',
    ];

    protected $casts = [
        'batas_persen' => 'float',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsToMany<\App\Models\MasterKodeRekening, $this> */
    public function kodeRekenings(): BelongsToMany
    {
        return $this->belongsToMany(
            MasterKodeRekening::class,
            'kode_rekening_kategori_juknis',
            'kategori_juknis_id',
            'kode_rekening_id',
        );
    }
}
