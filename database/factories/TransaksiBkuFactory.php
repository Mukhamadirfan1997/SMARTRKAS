<?php

namespace Database\Factories;

use App\Models\RkasItem;
use App\Models\SumberDana;
use App\Models\TahunAnggaran;
use App\Models\TransaksiBku;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TransaksiBku>
 */
class TransaksiBkuFactory extends Factory
{
    public function definition(): array
    {
        $jenis = fake()->randomElement(['penerimaan', 'pengeluaran']);

        return [
            'tahun_anggaran_id' => TahunAnggaran::factory(),
            'sumber_dana_id' => SumberDana::factory(),
            'rkas_item_id' => RkasItem::factory(),
            'tanggal' => fake()->date(),
            'bulan' => fake()->numberBetween(1, 12),
            'no_bukti' => fake()->unique()->numerify('BKU-####-#####'),
            'jenis' => $jenis,
            'jumlah' => fake()->randomFloat(2, 10000, 5000000),
            'volume' => fake()->optional(0.7)->randomFloat(2, 1, 100),
            'satuan' => fake()->optional(0.7)->randomElement(['buah', 'paket', 'lembar', 'unit', 'set', 'kg', 'liter']),
            'toko_penerima' => $jenis === 'pengeluaran' ? fake()->company() : null,
            'metode_pengadaan' => $jenis === 'pengeluaran' ? fake()->randomElement(['siplah', 'non_siplah']) : null,
            'uraian' => fake()->sentence(5),
            'tahap' => 1,
            'status_lunas' => fake()->boolean(80),
            'created_by' => User::factory(),
        ];
    }
}
