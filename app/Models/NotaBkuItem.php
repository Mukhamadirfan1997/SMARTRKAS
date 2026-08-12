<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $nota_bku_id
 * @property string|null $rkas_item_id
 * @property int $urutan
 * @property float $jumlah
 * @property string|null $satuan
 * @property float $harga_satuan
 * @property float $subtotal
 * @use HasFactory<\Database\Factories\NotaBkuItemFactory>
 */
class NotaBkuItem extends Model
{
    /** @use HasFactory<\Database\Factories\NotaBkuItemFactory> */
    use HasFactory;
    use HasUuids;

    protected $table = 'nota_bku_item';

    protected $fillable = [
        'nota_bku_id',
        'rkas_item_id',
        'urutan',
        'jumlah',
        'satuan',
        'harga_satuan',
        'subtotal',
    ];

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\NotaBku, $this> */
    public function notaBku(): BelongsTo
    {
        return $this->belongsTo(NotaBku::class, 'nota_bku_id');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Models\RkasItem, $this> */
    public function rkasItem(): BelongsTo
    {
        return $this->belongsTo(RkasItem::class, 'rkas_item_id');
    }
}