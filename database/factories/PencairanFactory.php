<?php

namespace Database\Factories;

use App\Models\Pencairan;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pencairan>
 */
class PencairanFactory extends Factory
{
    public function definition(): array
    {
        $tanggal = fake()->dateTimeBetween('2026-01-01', '2026-12-31');

        return [
            'tahun_anggaran_id' => TahunAnggaran::factory(),
            'sumber_dana_id' => null,
            'tanggal' => $tanggal,
            'bulan' => (int) $tanggal->format('n'),
            'nominal' => fake()->randomFloat(2, 1000000, 50000000),
            'keterangan' => 'SP2D Tahap '.fake()->numberBetween(1, 4),
            'created_by' => User::factory(),
        ];
    }

    public function untukSumber(?SumberDana $sumber): static
    {
        return $this->state(fn () => ['sumber_dana_id' => $sumber?->id]);
    }
}
