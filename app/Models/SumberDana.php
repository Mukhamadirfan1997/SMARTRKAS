<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $kode
 * @property string $nama
 * @use HasFactory<\Database\Factories\SumberDanaFactory>
 */
class SumberDana extends Model
{
    /** @use HasFactory<\Database\Factories\SumberDanaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'sumber_dana';

    protected $fillable = [
        'kode',
        'nama',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RkasItem, $this> */
    public function rkasItems(): HasMany
    {
        return $this->hasMany(RkasItem::class, 'sumber_dana_id');
    }
}
