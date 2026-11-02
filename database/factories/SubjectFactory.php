<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Subject>
 */
class SubjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'code' => strtoupper(fake()->unique()->bothify('SUB-###')),
            'full_marks' => 100,
            'pass_marks' => 40,
            'is_active' => true,
        ];
    }
}
