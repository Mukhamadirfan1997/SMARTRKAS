<?php

namespace Database\Factories;

use App\Models\MasterKodeRekening;
use App\Models\MasterProgram;
use App\Models\SumberDana;
use App\Models\TransaksiTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransaksiTemplate>
 */
class TransaksiTemplateFactory extends Factory
{
    protected $model = TransaksiTemplate::class;

    public function definition(): array
    {
        return [
            'nama_template' => fake()->words(3, true),
            'kode_rekening_id' => MasterKodeRekening::factory(),
            'kegiatan_id' => MasterProgram::factory(),
            'uraian_item_snapshot' => fake()->sentence(4),
            'toko_penerima' => fake()->optional(0.7)->company(),
            'metode_pengadaan' => fake()->optional(0.7)->randomElement(['siplah', 'non_siplah']),
            'uraian_dasar' => fake()->optional(0.7)->sentence(3),
            'sumber_dana_id' => SumberDana::factory(),
            'created_by' => User::factory(),
        ];
    }
}
