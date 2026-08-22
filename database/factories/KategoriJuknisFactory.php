<?php

namespace Database\Factories;

use App\Models\KategoriJuknis;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\KategoriJuknis>
 */
class KategoriJuknisFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->words(2, true),
            'arah' => fake()->randomElement(['maksimal', 'minimal']),
            'batas_persen' => fake()->randomFloat(2, 1, 100),
            'berlaku_untuk' => null,
        ];
    }

    public function maksimal(float $batas = 50): static
    {
        return $this->state(fn (): array => [
            'arah' => 'maksimal',
            'batas_persen' => $batas,
        ]);
    }

    public function minimal(float $batas = 10): static
    {
        return $this->state(fn (): array => [
            'arah' => 'minimal',
            'batas_persen' => $batas,
        ]);
    }
}
