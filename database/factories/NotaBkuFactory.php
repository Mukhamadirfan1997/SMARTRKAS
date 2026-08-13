<?php

namespace Database\Factories;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\NotaBku;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotaBku>
 */
class NotaBkuFactory extends Factory
{
    public function definition(): array
    {
        return [
            'no_nota' => fake()->unique()->numerify('NOTA-####'),
            'tanggal' => fake()->date(),
            'bulan' => fake()->numberBetween(1, 12),
            'kegiatan_id' => MasterProgram::factory(),
            'kode_rekening_id' => MasterKodeRekening::factory(),
            'sumber_dana_id' => SumberDana::factory(),
            'tahun_anggaran_id' => TahunAnggaran::factory(),
            'toko_penerima' => fake()->company(),
            'metode_pengadaan' => fake()->randomElement(['siplah', 'non_siplah']),
            'uraian' => fake()->sentence(5),
            'created_by' => User::factory(),
        ];
    }
}