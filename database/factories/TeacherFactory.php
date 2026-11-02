<?php

namespace Database\Factories;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Teacher>
 */
class TeacherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_code' => Teacher::nextEmployeeCode(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'gender' => fake()->randomElement(['male', 'female']),
            'phone' => fake()->numerify('07########'),
            'employment_type' => 'full_time',
            'status' => 'active',
        ];
    }
}
