<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\MiscIncome;
use App\Models\SalaryPayment;
use App\Models\Teacher;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
    }

    public function test_it_computes_income_expense_and_net_balance_for_the_selected_month(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');

        [$year, $month] = JalaliDate::parts(JalaliDate::today());

        $invoice = FeeInvoice::factory()->create(['amount' => 1000]);
        FeePayment::factory()->create([
            'fee_invoice_id' => $invoice->id,
            'amount' => 600,
            'payment_date' => JalaliDate::today(),
        ]);

        $teacher = Teacher::factory()->create();
        SalaryPayment::factory()->create([
            'payee_type' => 'teacher',
            'payee_id' => $teacher->id,
            'amount' => 400,
            'payment_date' => JalaliDate::today(),
        ]);

        MiscIncome::factory()->create([
            'amount' => 250,
            'received_date' => JalaliDate::today(),
        ]);

        Expense::factory()->create([
            'amount' => 150,
            'expense_date' => JalaliDate::today(),
        ]);

        // Outside the selected month - must not be counted.
        FeePayment::factory()->create([
            'fee_invoice_id' => $invoice->id,
            'amount' => 9999,
            'payment_date' => JalaliDate::addDays(JalaliDate::today(), -400),
        ]);
        MiscIncome::factory()->create([
            'amount' => 9999,
            'received_date' => JalaliDate::addDays(JalaliDate::today(), -400),
        ]);
        Expense::factory()->create([
            'amount' => 9999,
            'expense_date' => JalaliDate::addDays(JalaliDate::today(), -400),
        ]);

        $response = $this->actingAs($accountant)->get(route('finance.overview', [
            'month' => $month,
            'year' => $year,
        ]));

        $response->assertStatus(200);
        $response->assertViewHas('totalFeeIncome', 600.0);
        $response->assertViewHas('totalMiscIncome', 250.0);
        $response->assertViewHas('totalIncome', 850.0);
        $response->assertViewHas('totalSalaryExpense', 400.0);
        $response->assertViewHas('totalOtherExpense', 150.0);
        $response->assertViewHas('totalExpense', 550.0);
        $response->assertViewHas('netBalance', 300.0);
    }

    public function test_a_teacher_cannot_view_the_finance_overview(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get(route('finance.overview'))->assertStatus(403);
    }

    public function test_a_principal_can_view_the_finance_overview(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->get(route('finance.overview'))->assertStatus(200);
    }
}
