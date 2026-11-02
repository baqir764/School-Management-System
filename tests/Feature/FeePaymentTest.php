<?php

namespace Tests\Feature;

use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;

    protected array $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());
        $this->today = ['year' => $year, 'month' => $month, 'day' => $day];
    }

    public function test_recording_a_payment_reduces_the_invoice_balance(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 1000]);

        $response = $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 400,
            'payment_date' => $this->today,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('fee_payments', ['fee_invoice_id' => $invoice->id, 'amount' => 400]);

        $invoice->refresh()->load('payments');
        $this->assertEquals(600, $invoice->balance());
        $this->assertEquals('partial', $invoice->status());
    }

    public function test_a_payment_that_exactly_covers_the_balance_marks_the_invoice_paid(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);

        $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 500,
            'payment_date' => $this->today,
            'payment_method' => 'bank_transfer',
        ]);

        $invoice->refresh()->load('payments');
        $this->assertEquals(0, $invoice->balance());
        $this->assertEquals('paid', $invoice->status());
    }

    public function test_a_payment_cannot_exceed_the_remaining_balance(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);

        $response = $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 600,
            'payment_date' => $this->today,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('fee_payments', 0);
    }

    public function test_cannot_record_a_payment_against_an_already_fully_paid_invoice(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);
        FeePayment::factory()->create(['fee_invoice_id' => $invoice->id, 'amount' => 500]);

        $response = $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 50,
            'payment_date' => $this->today,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('fee_payments', 1);
    }

    public function test_receipt_numbers_are_sequential_and_unique(): void
    {
        $invoiceOne = FeeInvoice::factory()->create(['amount' => 1000]);
        $invoiceTwo = FeeInvoice::factory()->create(['amount' => 1000]);

        $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoiceOne), [
            'amount' => 100, 'payment_date' => $this->today, 'payment_method' => 'cash',
        ]);
        $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoiceTwo), [
            'amount' => 100, 'payment_date' => $this->today, 'payment_method' => 'cash',
        ]);

        $receiptNumbers = FeePayment::query()->pluck('receipt_number')->all();
        $this->assertCount(2, array_unique($receiptNumbers));
    }

    public function test_a_receptionist_can_collect_payments_but_not_manage_fee_structures(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 100, 'payment_date' => $this->today, 'payment_method' => 'cash',
        ])->assertRedirect();

        $this->actingAs($receptionist)->get(route('fee-structures.create'))->assertStatus(403);
    }

    public function test_a_principal_cannot_record_a_payment_but_can_view_the_receipt(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);
        $payment = FeePayment::factory()->create(['fee_invoice_id' => $invoice->id, 'amount' => 100]);

        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 50, 'payment_date' => $this->today, 'payment_method' => 'cash',
        ])->assertStatus(403);

        $this->actingAs($principal)->get(route('fee-payments.receipt', $payment))->assertStatus(200);
    }
}
