<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $nama
 * @use HasFactory<\Database\Factories\JenisBelanjaFactory>
 */
class JenisBelanja extends Model
{
    /** @use HasFactory<\Database\Factories\JenisBelanjaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'jenis_belanja';

    protected $fillable = [
        'nama',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\MasterKodeRekening, $this> */
    public function masterKodeRekenings(): HasMany
    {
        return $this->hasMany(MasterKodeRekening::class);
    }
}
