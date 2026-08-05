<?php

namespace Database\Factories;

use App\Models\JenisBelanja;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\JenisBelanja>
 */
class JenisBelanjaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nama' => fake()->unique()->randomElement([
                'Belanja Barang',
                'Belanja Jasa',
                'Belanja Modal',
                'Belanja Pegawai',
            ]),
        ];
    }
}
