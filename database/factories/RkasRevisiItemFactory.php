<?php

namespace Database\Factories;

use App\Models\RkasItem;
use App\Models\RkasRevisi;
use App\Models\RkasRevisiItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RkasRevisiItem>
 */
class RkasRevisiItemFactory extends Factory
{
    public function definition(): array
    {
        $sebelum = fake()->randomFloat(2, 1000, 1000000);

        return [
            'rkas_revisi_id' => RkasRevisi::factory(),
            'rkas_item_id' => RkasItem::factory(),
            'bulan' => fake()->numberBetween(1, 12),
            'arah' => fake()->randomElement(['naik', 'turun']),
            'sebelum' => $sebelum,
            'sesudah' => $sebelum,
            'delta' => 0,
            'urutan' => fake()->numberBetween(1, 20),
        ];
    }
}
