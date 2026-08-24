<?php

namespace Database\Factories;

use App\Models\KasPenutupan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KasPenutupan>
 */
class KasPenutupanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tahun_anggaran_id' => TahunAnggaran::factory(),
            'bulan' => fake()->numberBetween(1, 12),
            'sumber_dana_id' => null,
            'tanggal_penutupan' => fake()->date(),
            'lembar_100000' => fake()->numberBetween(0, 5),
            'lembar_50000' => fake()->numberBetween(0, 10),
            'lembar_20000' => fake()->numberBetween(0, 10),
            'lembar_10000' => fake()->numberBetween(0, 20),
            'lembar_5000' => fake()->numberBetween(0, 20),
            'lembar_2000' => fake()->numberBetween(0, 20),
            'lembar_1000' => fake()->numberBetween(0, 50),
            'keping_500' => fake()->numberBetween(0, 20),
            'keping_200' => fake()->numberBetween(0, 20),
            'keping_100' => fake()->numberBetween(0, 30),
            'keping_50' => fake()->numberBetween(0, 30),
            'saldo_bank' => fake()->randomFloat(2, 0, 5000000),
            'catatan' => null,
            'created_by' => User::factory(),
        ];
    }

    public function untukSumber(?SumberDana $sumber): static
    {
        return $this->state(fn () => ['sumber_dana_id' => $sumber?->id]);
    }
}
