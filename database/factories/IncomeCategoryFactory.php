<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\IncomeCategory>
 */
class IncomeCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Donation', 'Grant', 'Hall Rental', 'Canteen', 'Other']).' '.$this->faker->unique()->numberBetween(1, 9999),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
