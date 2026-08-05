<?php

namespace Database\Factories;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\RkasItem;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RkasItem>
 */
class RkasItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tahun_anggaran_id' => TahunAnggaran::factory(),
            'no_urut' => fake()->numberBetween(1, 999),
            'uraian' => fake()->sentence(6),
            'program_id' => MasterProgram::factory(),
            'kode_rekening_id' => MasterKodeRekening::factory(),
            'sumber_dana_id' => SumberDana::factory(),
            'volume' => fake()->randomFloat(2, 1, 100),
            'satuan' => fake()->randomElement(['buah', 'paket', 'kali', 'set', 'lembar']),
            'tarif' => fake()->randomFloat(2, 1000, 100000),
            'jumlah' => fake()->randomFloat(2, 10000, 10000000),
        ];
    }
}
