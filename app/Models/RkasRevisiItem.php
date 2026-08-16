<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $rkas_revisi_id
 * @property string|null $rkas_item_id
 * @property int $bulan
 * @property string $arah
 * @property float $sebelum
 * @property float $sesudah
 * @property float $delta
 * @property int $urutan
 * @use HasFactory<\Database\Factories\RkasRevisiItemFactory>
 */
class RkasRevisiItem extends Model
{
    /** @use HasFactory<\Database\Factories\RkasRevisiItemFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'rkas_revisi_item';

    protected $fillable = [
        'rkas_revisi_id',
        'rkas_item_id',
        'bulan',
        'arah',
        'sebelum',
        'sesudah',
        'delta',
        'urutan',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\RkasRevisi, $this> */
    public function rkasRevisi(): BelongsTo
    {
        return $this->belongsTo(RkasRevisi::class, 'rkas_revisi_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\RkasItem, $this> */
    public function rkasItem(): BelongsTo
    {
        return $this->belongsTo(RkasItem::class, 'rkas_item_id');
    }
}
