<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employee>
 */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'employee_code' => Employee::nextEmployeeCode(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'position' => fake()->randomElement(['Accountant', 'Guard', 'Cleaner', 'Receptionist', 'Driver']),
            'phone' => fake()->numerify('07########'),
            'monthly_salary' => fake()->numberBetween(3000, 15000),
            'joining_date' => JalaliDate::today(),
            'status' => 'active',
        ];
    }
}
