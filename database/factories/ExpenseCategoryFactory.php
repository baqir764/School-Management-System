<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement(['Utilities', 'Maintenance', 'Supplies', 'Transport', 'Stationery']).' '.$this->faker->unique()->numberBetween(1, 9999),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
