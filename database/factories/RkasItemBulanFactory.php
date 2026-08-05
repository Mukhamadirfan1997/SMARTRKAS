<?php

namespace Database\Factories;

use App\Models\RkasItem;
use App\Models\RkasItemBulan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RkasItemBulan>
 */
class RkasItemBulanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'rkas_item_id' => RkasItem::factory(),
            'bulan' => fake()->numberBetween(1, 12),
            'rencana' => fake()->randomFloat(2, 0, 2000000),
        ];
    }
}
