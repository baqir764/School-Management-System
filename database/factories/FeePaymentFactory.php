<?php

namespace Database\Factories;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Support\JalaliDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FeePayment>
 */
class FeePaymentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fee_invoice_id' => FeeInvoice::factory(),
            'amount' => fake()->numberBetween(100, 1000),
            'payment_date' => JalaliDate::today(),
            'payment_method' => 'cash',
            'receipt_number' => FeePayment::nextReceiptNumber(),
        ];
    }
}
