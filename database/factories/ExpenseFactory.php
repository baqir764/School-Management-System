<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expense>
 */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'expense_category_id' => ExpenseCategory::factory(),
            'title' => $this->faker->sentence(3),
            'amount' => $this->faker->numberBetween(500, 20000),
            'expense_date' => JalaliDate::today(),
            'payment_method' => $this->faker->randomElement(Expense::METHODS),
            'voucher_number' => Expense::nextVoucherNumber(),
            'remarks' => $this->faker->optional()->sentence(),
        ];
    }
}
