<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeeStructure>
 */
class FeeStructureFactory extends Factory
{
    public function definition(): array
    {
        return [
            'academic_year_id' => AcademicYear::factory(),
            'class_id' => null,
            'name' => fake()->unique()->words(2, true).' Fee',
            'amount' => fake()->numberBetween(500, 5000),
            'frequency' => 'term',
            'is_active' => true,
        ];
    }
}
