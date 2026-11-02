<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;

    protected array $expenseDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());
        $this->expenseDate = ['year' => $year, 'month' => $month, 'day' => $day];
    }

    public function test_an_accountant_can_create_an_expense_category(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('expense-categories.store'), [
            'name' => 'Utilities',
            'description' => 'Electricity and water',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('expense-categories.index'));
        $this->assertDatabaseHas('expense_categories', ['name' => 'Utilities', 'is_active' => 1]);
    }

    public function test_recording_an_expense_creates_it_with_a_voucher_number(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->actingAs($this->accountant)->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'title' => 'Diesel for generator',
            'amount' => 350,
            'expense_date' => $this->expenseDate,
            'payment_method' => 'cash',
        ]);

        $expense = Expense::query()->where('title', 'Diesel for generator')->firstOrFail();

        $response->assertRedirect(route('expenses.voucher', $expense));
        $this->assertDatabaseHas('expenses', [
            'expense_category_id' => $category->id,
            'amount' => 350,
            'paid_by' => $this->accountant->id,
        ]);
        $this->assertStringStartsWith('EXP-', $expense->voucher_number);
    }

    public function test_voucher_numbers_are_sequential_and_unique(): void
    {
        $category = ExpenseCategory::factory()->create();

        foreach (range(1, 2) as $i) {
            $this->actingAs($this->accountant)->post(route('expenses.store'), [
                'expense_category_id' => $category->id,
                'title' => "Expense {$i}",
                'amount' => 100,
                'expense_date' => $this->expenseDate,
                'payment_method' => 'cash',
            ]);
        }

        $voucherNumbers = Expense::query()->pluck('voucher_number')->all();
        $this->assertCount(2, array_unique($voucherNumbers));
    }

    public function test_an_expense_dated_in_the_future_fails_validation(): void
    {
        $category = ExpenseCategory::factory()->create();
        $futureDate = JalaliDate::parts(JalaliDate::addDays(JalaliDate::today(), 10));

        $response = $this->actingAs($this->accountant)->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'title' => 'Too early',
            'amount' => 100,
            'expense_date' => ['year' => $futureDate[0], 'month' => $futureDate[1], 'day' => $futureDate[2]],
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors('expense_date.day');
        $this->assertDatabaseCount('expenses', 0);
    }

    public function test_an_expense_category_in_use_cannot_be_deleted(): void
    {
        $category = ExpenseCategory::factory()->create();
        Expense::factory()->create(['expense_category_id' => $category->id]);

        $response = $this->actingAs($this->accountant)->delete(route('expense-categories.destroy', $category));

        $response->assertStatus(403);
        $this->assertDatabaseHas('expense_categories', ['id' => $category->id]);
    }

    public function test_an_unused_expense_category_can_be_deleted(): void
    {
        $category = ExpenseCategory::factory()->create();

        $response = $this->actingAs($this->accountant)->delete(route('expense-categories.destroy', $category));

        $response->assertRedirect(route('expense-categories.index'));
        $this->assertDatabaseMissing('expense_categories', ['id' => $category->id]);
    }

    public function test_a_receptionist_has_no_finance_access(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->get(route('expenses.index'))->assertStatus(403);
        $this->actingAs($receptionist)->post(route('expenses.store'), [
            'expense_category_id' => ExpenseCategory::factory()->create()->id,
            'title' => 'Should not be allowed',
            'amount' => 50,
            'expense_date' => $this->expenseDate,
            'payment_method' => 'cash',
        ])->assertStatus(403);
    }

    public function test_a_principal_can_view_expenses_but_not_record_or_manage_categories(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');
        $category = ExpenseCategory::factory()->create();

        $this->actingAs($principal)->get(route('expenses.index'))->assertStatus(200);
        $this->actingAs($principal)->get(route('expense-categories.index'))->assertStatus(200);
        $this->actingAs($principal)->get(route('expenses.create'))->assertStatus(403);
        $this->actingAs($principal)->post(route('expense-categories.store'), ['name' => 'Nope'])->assertStatus(403);
        $this->actingAs($principal)->post(route('expenses.store'), [
            'expense_category_id' => $category->id,
            'title' => 'Not allowed',
            'amount' => 50,
            'expense_date' => $this->expenseDate,
            'payment_method' => 'cash',
        ])->assertStatus(403);
    }
}
