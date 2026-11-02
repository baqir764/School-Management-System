<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ShiftFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement(['Morning', 'Afternoon', 'Evening']).' '.$this->faker->unique()->numberBetween(1, 9999),
            'start_time' => $this->faker->randomElement(['07:00', '07:30', '08:00', '12:30', '13:00']),
            'is_active' => true,
        ];
    }
}
