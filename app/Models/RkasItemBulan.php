<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $rkas_item_id
 * @property int $bulan
 * @property float $rencana
 * @use HasFactory<\Database\Factories\RkasItemBulanFactory>
 */
class RkasItemBulan extends Model
{
    /** @use HasFactory<\Database\Factories\RkasItemBulanFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'rkas_item_bulan';

    protected $fillable = [
        'rkas_item_id',
        'bulan',
        'rencana',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\RkasItem, $this> */
    public function rkasItem(): BelongsTo
    {
        return $this->belongsTo(RkasItem::class);
    }
}
