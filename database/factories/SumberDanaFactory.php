<?php

namespace Database\Factories;

use App\Models\SumberDana;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SumberDana>
 */
class SumberDanaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('SD-####'),
            'nama' => fake()->unique()->words(3, true),
        ];
    }
}
