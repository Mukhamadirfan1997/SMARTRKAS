<?php

namespace Database\Factories;

use App\Models\NotaBku;
use App\Models\NotaBkuItem;
use App\Models\RkasItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotaBkuItem>
 */
class NotaBkuItemFactory extends Factory
{
    public function definition(): array
    {
        $jumlah = fake()->randomFloat(2, 1, 100);
        $hargaSatuan = fake()->randomFloat(2, 1000, 100000);

        return [
            'nota_bku_id' => NotaBku::factory(),
            'rkas_item_id' => RkasItem::factory(),
            'urutan' => fake()->numberBetween(1, 20),
            'jumlah' => $jumlah,
            'satuan' => fake()->randomElement(['buah', 'paket', 'lembar', 'unit', 'set', 'kali']),
            'harga_satuan' => $hargaSatuan,
            'subtotal' => round($jumlah * $hargaSatuan, 2),
        ];
    }
}