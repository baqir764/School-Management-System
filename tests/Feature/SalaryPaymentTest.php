<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\Teacher;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalaryPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;

    protected array $salaryMonth;

    protected array $paymentDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());
        $this->salaryMonth = ['month' => $month, 'year' => $year];
        $this->paymentDate = ['year' => $year, 'month' => $month, 'day' => $day];
    }

    public function test_paying_a_teacher_creates_a_salary_payment(): void
    {
        $teacher = Teacher::factory()->create(['monthly_salary' => 7000]);

        $response = $this->actingAs($this->accountant)->post(route('salary-payments.store'), [
            'payee_type' => 'teacher',
            'payee_id' => $teacher->id,
            'amount' => 7000,
            'salary_month' => $this->salaryMonth,
            'payment_date' => $this->paymentDate,
            'payment_method' => 'bank_transfer',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('salary_payments', [
            'payee_type' => 'teacher',
            'payee_id' => $teacher->id,
            'amount' => 7000,
        ]);
    }

    public function test_paying_an_employee_creates_a_salary_payment(): void
    {
        $employee = Employee::factory()->create(['monthly_salary' => 5000]);

        $response = $this->actingAs($this->accountant)->post(route('salary-payments.store'), [
            'payee_type' => 'employee',
            'payee_id' => $employee->id,
            'amount' => 5000,
            'salary_month' => $this->salaryMonth,
            'payment_date' => $this->paymentDate,
            'payment_method' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('salary_payments', [
            'payee_type' => 'employee',
            'payee_id' => $employee->id,
            'amount' => 5000,
        ]);
    }

    public function test_cannot_pay_the_same_payee_twice_for_the_same_month(): void
    {
        $teacher = Teacher::factory()->create(['monthly_salary' => 7000]);
        SalaryPayment::factory()->create([
            'payee_type' => 'teacher',
            'payee_id' => $teacher->id,
            'salary_month' => sprintf('%04d-%02d', $this->salaryMonth['year'], $this->salaryMonth['month']),
        ]);

        $response = $this->actingAs($this->accountant)->post(route('salary-payments.store'), [
            'payee_type' => 'teacher',
            'payee_id' => $teacher->id,
            'amount' => 7000,
            'salary_month' => $this->salaryMonth,
            'payment_date' => $this->paymentDate,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('salary_month.month');
        $this->assertDatabaseCount('salary_payments', 1);
    }

    public function test_paying_a_nonexistent_payee_fails_validation(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('salary-payments.store'), [
            'payee_type' => 'employee',
            'payee_id' => 999999,
            'amount' => 1000,
            'salary_month' => $this->salaryMonth,
            'payment_date' => $this->paymentDate,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('payee_id');
        $this->assertDatabaseCount('salary_payments', 0);
    }

    public function test_voucher_numbers_are_sequential_and_unique(): void
    {
        $teacherOne = Teacher::factory()->create();
        $teacherTwo = Teacher::factory()->create();

        $this->actingAs($this->accountant)->post(route('salary-payments.store'), [
            'payee_type' => 'teacher', 'payee_id' => $teacherOne->id, 'amount' => 100,
            'salary_month' => $this->salaryMonth, 'payment_date' => $this->paymentDate, 'payment_method' => 'cash',
        ]);
        $this->actingAs($this->accountant)->post(route('salary-payments.store'), [
            'payee_type' => 'teacher', 'payee_id' => $teacherTwo->id, 'amount' => 100,
            'salary_month' => $this->salaryMonth, 'payment_date' => $this->paymentDate, 'payment_method' => 'cash',
        ]);

        $voucherNumbers = SalaryPayment::query()->pluck('voucher_number')->all();
        $this->assertCount(2, array_unique($voucherNumbers));
    }

    public function test_a_receptionist_has_no_payroll_access(): void
    {
        $teacher = Teacher::factory()->create(['monthly_salary' => 7000]);
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->post(route('salary-payments.store'), [
            'payee_type' => 'teacher', 'payee_id' => $teacher->id, 'amount' => 7000,
            'salary_month' => $this->salaryMonth, 'payment_date' => $this->paymentDate, 'payment_method' => 'cash',
        ])->assertStatus(403);
    }

    public function test_a_principal_cannot_submit_a_payment_but_can_view_the_receipt(): void
    {
        $payment = SalaryPayment::factory()->create();
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->post(route('salary-payments.store'), [
            'payee_type' => 'teacher', 'payee_id' => $payment->payee_id, 'amount' => 100,
            'salary_month' => $this->salaryMonth, 'payment_date' => $this->paymentDate, 'payment_method' => 'cash',
        ])->assertStatus(403);

        $this->actingAs($principal)->get(route('salary-payments.receipt', $payment))->assertStatus(200);
    }
}
