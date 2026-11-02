<?php

namespace Tests\Feature;

use App\Models\IncomeCategory;
use App\Models\MiscIncome;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MiscIncomeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;

    protected array $receivedDate;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());
        $this->receivedDate = ['year' => $year, 'month' => $month, 'day' => $day];
    }

    public function test_an_accountant_can_create_an_income_category(): void
    {
        $response = $this->actingAs($this->accountant)->post(route('income-categories.store'), [
            'name' => 'Donation',
            'description' => 'Community donations',
            'is_active' => 1,
        ]);

        $response->assertRedirect(route('income-categories.index'));
        $this->assertDatabaseHas('income_categories', ['name' => 'Donation', 'is_active' => 1]);
    }

    public function test_recording_a_misc_income_creates_it_with_a_receipt_number(): void
    {
        $category = IncomeCategory::factory()->create();

        $response = $this->actingAs($this->accountant)->post(route('misc-incomes.store'), [
            'income_category_id' => $category->id,
            'title' => 'Hall rental',
            'amount' => 500,
            'received_date' => $this->receivedDate,
            'payment_method' => 'bank_transfer',
        ]);

        $income = MiscIncome::query()->where('title', 'Hall rental')->firstOrFail();

        $response->assertRedirect(route('misc-incomes.receipt', $income));
        $this->assertDatabaseHas('misc_incomes', [
            'income_category_id' => $category->id,
            'amount' => 500,
            'received_by' => $this->accountant->id,
        ]);
        $this->assertStringStartsWith('MISC-', $income->receipt_number);
    }

    public function test_receipt_numbers_are_sequential_and_unique(): void
    {
        $category = IncomeCategory::factory()->create();

        foreach (range(1, 2) as $i) {
            $this->actingAs($this->accountant)->post(route('misc-incomes.store'), [
                'income_category_id' => $category->id,
                'title' => "Income {$i}",
                'amount' => 100,
                'received_date' => $this->receivedDate,
                'payment_method' => 'cash',
            ]);
        }

        $receiptNumbers = MiscIncome::query()->pluck('receipt_number')->all();
        $this->assertCount(2, array_unique($receiptNumbers));
    }

    public function test_an_income_category_in_use_cannot_be_deleted(): void
    {
        $category = IncomeCategory::factory()->create();
        MiscIncome::factory()->create(['income_category_id' => $category->id]);

        $response = $this->actingAs($this->accountant)->delete(route('income-categories.destroy', $category));

        $response->assertStatus(403);
        $this->assertDatabaseHas('income_categories', ['id' => $category->id]);
    }

    public function test_an_unused_income_category_can_be_deleted(): void
    {
        $category = IncomeCategory::factory()->create();

        $response = $this->actingAs($this->accountant)->delete(route('income-categories.destroy', $category));

        $response->assertRedirect(route('income-categories.index'));
        $this->assertDatabaseMissing('income_categories', ['id' => $category->id]);
    }

    public function test_a_receptionist_has_no_finance_access(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->get(route('misc-incomes.index'))->assertStatus(403);
        $this->actingAs($receptionist)->post(route('misc-incomes.store'), [
            'income_category_id' => IncomeCategory::factory()->create()->id,
            'title' => 'Should not be allowed',
            'amount' => 50,
            'received_date' => $this->receivedDate,
            'payment_method' => 'cash',
        ])->assertStatus(403);
    }

    public function test_a_principal_can_view_misc_incomes_but_not_record_or_manage_categories(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');
        $category = IncomeCategory::factory()->create();

        $this->actingAs($principal)->get(route('misc-incomes.index'))->assertStatus(200);
        $this->actingAs($principal)->get(route('income-categories.index'))->assertStatus(200);
        $this->actingAs($principal)->get(route('misc-incomes.create'))->assertStatus(403);
        $this->actingAs($principal)->post(route('income-categories.store'), ['name' => 'Nope'])->assertStatus(403);
        $this->actingAs($principal)->post(route('misc-incomes.store'), [
            'income_category_id' => $category->id,
            'title' => 'Not allowed',
            'amount' => 50,
            'received_date' => $this->receivedDate,
            'payment_method' => 'cash',
        ])->assertStatus(403);
    }
}
