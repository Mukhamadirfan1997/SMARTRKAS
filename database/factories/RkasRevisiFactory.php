<?php

namespace Database\Factories;

use App\Models\RkasRevisi;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RkasRevisi>
 */
class RkasRevisiFactory extends Factory
{
    public function definition(): array
    {
        return [
            'no_revisi' => fake()->unique()->numerify('PGS-####'),
            'jenis' => 'pergeseran',
            'tanggal' => fake()->date(),
            'tahun_anggaran_id' => TahunAnggaran::factory(),
            'sumber_dana_id' => SumberDana::factory(),
            'keterangan' => fake()->sentence(4),
            'sebelum_total' => fake()->randomFloat(2, 1000, 1000000),
            'sesudah_total' => fake()->randomFloat(2, 1000, 1000000),
            'data_perubahan' => null,
            'created_by' => User::factory(),
        ];
    }
}
