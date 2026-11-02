<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');
    }

    public function test_administrator_can_create_an_employee_with_an_auto_generated_code(): void
    {
        $response = $this->actingAs($this->admin)->post(route('employees.store'), [
            'first_name' => 'Farida',
            'last_name' => 'Noori',
            'position' => 'Accountant',
            'monthly_salary' => 8000,
            'status' => 'active',
        ]);

        $response->assertRedirect(route('employees.index'));
        $this->assertDatabaseHas('employees', [
            'first_name' => 'Farida',
            'last_name' => 'Noori',
            'position' => 'Accountant',
        ]);

        $employee = Employee::first();
        $this->assertStringStartsWith('STF-', $employee->employee_code);
    }

    public function test_employee_codes_are_sequential(): void
    {
        Employee::factory()->create(['employee_code' => 'STF-0001']);

        $this->actingAs($this->admin)->post(route('employees.store'), [
            'first_name' => 'Karim',
            'last_name' => 'Ahmadi',
            'position' => 'Guard',
            'monthly_salary' => 5000,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('employees', ['employee_code' => 'STF-0002']);
    }

    public function test_an_accountant_can_manage_employees(): void
    {
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');

        $this->actingAs($accountant)->post(route('employees.store'), [
            'first_name' => 'Zainab',
            'last_name' => 'Rahimi',
            'position' => 'Receptionist',
            'monthly_salary' => 6000,
            'status' => 'active',
        ])->assertRedirect(route('employees.index'));
    }

    public function test_a_principal_can_view_but_not_manage_employees(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->get(route('employees.index'))->assertStatus(200);
        $this->actingAs($principal)->get(route('employees.create'))->assertStatus(403);
    }

    public function test_a_receptionist_has_no_access_to_employees(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->get(route('employees.index'))->assertStatus(403);
    }

    public function test_cannot_delete_an_employee_with_salary_payment_history(): void
    {
        $employee = Employee::factory()->create();
        SalaryPayment::factory()->create(['payee_type' => 'employee', 'payee_id' => $employee->id]);

        $this->actingAs($this->admin)->delete(route('employees.destroy', $employee))->assertStatus(403);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'deleted_at' => null]);
    }

    public function test_an_employee_with_no_payment_history_can_be_deleted(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)->delete(route('employees.destroy', $employee))->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
    }

    public function test_administrator_can_print_an_employee_id_card(): void
    {
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('employees.id-card', $employee));

        $response->assertOk();
        // The back side carries the scannable staff payload.
        $this->assertStringContainsString('&quot;t&quot;:&quot;employee&quot;', $response->getContent());
        $this->assertStringContainsString($employee->employee_code, $response->getContent());
    }
}
