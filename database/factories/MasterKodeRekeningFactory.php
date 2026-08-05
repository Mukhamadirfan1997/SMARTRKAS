<?php

namespace Database\Factories;

use App\Models\JenisBelanja;
use App\Models\MasterKodeRekening;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MasterKodeRekening>
 */
class MasterKodeRekeningFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->numerify('5.1.2.##.####'),
            'nama' => fake()->unique()->words(4, true),
            'jenis_belanja_id' => JenisBelanja::factory(),
        ];
    }
}
