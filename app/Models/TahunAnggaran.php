<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

/**
 * @property string $id
 * @property int $tahun
 * @property bool $status
 * @use HasFactory<\Database\Factories\TahunAnggaranFactory>
 */
class TahunAnggaran extends Model
{
    /** @use HasFactory<\Database\Factories\TahunAnggaranFactory> */
    use HasFactory, HasUuids;

    protected $table = 'tahun_anggaran';

    protected $fillable = [
        'tahun',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
        ];
    }

    public static function getActive(): ?self
    {
        if (app()->runningUnitTests()) {
            return static::where('status', true)->first();
        }

        return Cache::remember('tahun_anggaran_active', 86400, fn (): ?self => static::where('status', true)->first());
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\RkasItem, $this> */
    public function rkasItems(): HasMany
    {
        return $this->hasMany(RkasItem::class);
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ImportLog, $this> */
    public function importLogs(): HasMany
    {
        return $this->hasMany(ImportLog::class);
    }
}
