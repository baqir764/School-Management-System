<?php

namespace Database\Factories;

use App\Models\IncomeCategory;
use App\Models\MiscIncome;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MiscIncome>
 */
class MiscIncomeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'income_category_id' => IncomeCategory::factory(),
            'title' => $this->faker->sentence(3),
            'amount' => $this->faker->numberBetween(500, 20000),
            'received_date' => JalaliDate::today(),
            'payment_method' => $this->faker->randomElement(MiscIncome::METHODS),
            'receipt_number' => MiscIncome::nextReceiptNumber(),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }
}
