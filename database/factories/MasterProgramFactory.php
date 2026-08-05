<?php

namespace Database\Factories;

use App\Models\MasterProgram;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MasterProgram>
 */
class MasterProgramFactory extends Factory
{
    public function definition(): array
    {
        return [
            'kode' => fake()->unique()->bothify('P.####'),
            'nama' => fake()->unique()->words(4, true),
            'program' => null,
            'sub_program' => null,
            'parent_id' => null,
            'level' => 1,
        ];
    }
}
