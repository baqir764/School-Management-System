<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\FeeStructure;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeStructureTest extends TestCase
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

    public function test_administrator_can_create_a_school_wide_fee_head(): void
    {
        $year = AcademicYear::factory()->create();

        $response = $this->actingAs($this->admin)->post('/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'Admission Fee',
            'amount' => 1500,
            'frequency' => 'one_time',
        ]);

        $response->assertRedirect('/fee-structures');
        $this->assertDatabaseHas('fee_structures', [
            'academic_year_id' => $year->id,
            'class_id' => null,
            'name' => 'Admission Fee',
        ]);
    }

    public function test_administrator_can_create_a_class_specific_fee_head(): void
    {
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();

        $response = $this->actingAs($this->admin)->post('/fee-structures', [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'Lab Fee',
            'amount' => 300,
            'frequency' => 'term',
        ]);

        $response->assertRedirect('/fee-structures');
        $this->assertDatabaseHas('fee_structures', [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'Lab Fee',
        ]);
    }

    public function test_the_same_fee_name_cannot_be_defined_twice_for_the_same_year_and_scope(): void
    {
        $existing = FeeStructure::factory()->create(['name' => 'Tuition Fee', 'class_id' => null]);

        $response = $this->actingAs($this->admin)->post('/fee-structures', [
            'academic_year_id' => $existing->academic_year_id,
            'name' => 'Tuition Fee',
            'amount' => 1000,
            'frequency' => 'term',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_the_same_fee_name_is_allowed_for_a_different_class_in_the_same_year(): void
    {
        $existing = FeeStructure::factory()->create(['name' => 'Lab Fee']);
        $class = ClassRoom::factory()->create();

        $response = $this->actingAs($this->admin)->post('/fee-structures', [
            'academic_year_id' => $existing->academic_year_id,
            'class_id' => $class->id,
            'name' => 'Lab Fee',
            'amount' => 300,
            'frequency' => 'term',
        ]);

        $response->assertRedirect('/fee-structures');
        $this->assertDatabaseCount('fee_structures', 2);
    }

    public function test_an_accountant_can_manage_fee_structure(): void
    {
        $account = User::factory()->create();
        $account->assignRole('Accountant');
        $year = AcademicYear::factory()->create();

        $this->actingAs($account)->post('/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'Transport Fee',
            'amount' => 800,
            'frequency' => 'monthly',
        ])->assertRedirect('/fee-structures');
    }

    public function test_a_teacher_cannot_view_or_manage_fee_structure(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/fee-structures')->assertStatus(403);
    }

    public function test_a_principal_can_view_but_not_manage_fee_structure(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');
        $year = AcademicYear::factory()->create();

        $this->actingAs($principal)->get('/fee-structures')->assertStatus(200);
        $this->actingAs($principal)->post('/fee-structures', [
            'academic_year_id' => $year->id,
            'name' => 'Sports Fee',
            'amount' => 200,
            'frequency' => 'annual',
        ])->assertStatus(403);
    }
}
