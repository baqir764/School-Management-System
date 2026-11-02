<?php

namespace Database\Factories;

use App\Models\SalaryPayment;
use App\Models\Teacher;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalaryPayment>
 */
class SalaryPaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'payee_type' => 'teacher',
            'payee_id' => Teacher::factory(),
            'amount' => fake()->numberBetween(3000, 15000),
            'salary_month' => substr(JalaliDate::today(), 0, 7),
            'payment_date' => JalaliDate::today(),
            'payment_method' => 'cash',
            'voucher_number' => SalaryPayment::nextVoucherNumber(),
        ];
    }
}
