<?php

namespace Database\Factories;

use App\Models\ExportJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExportJob> */
class ExportJobFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['BKU', 'Rekap Realisasi', 'Rekap Tribulan', 'Rekap SIPLAH']),
            'status' => 'processing',
            'filename' => null,
            'file_path' => null,
            'error_message' => null,
            'completed_at' => null,
        ];
    }
}
