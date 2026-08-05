<?php

namespace Database\Factories;

use App\Models\ImportLog;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportLog>
 */
class ImportLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tahun_anggaran_id' => TahunAnggaran::factory(),
            'sumber_dana_id' => SumberDana::factory(),
            'bulan' => fake()->numberBetween(1, 12),
            'file_name' => fake()->numerify('JANUARI.xlsx'),
            'status' => 'sukses',
            'total_baris' => fake()->numberBetween(1, 100),
            'baris_berhasil' => fake()->numberBetween(0, 100),
            'baris_gagal' => 0,
            'uploaded_by' => User::factory(),
            'finished_at' => now(),
        ];
    }
}
