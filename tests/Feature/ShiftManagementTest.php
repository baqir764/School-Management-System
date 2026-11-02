<?php

namespace Tests\Feature;

use App\Models\ClassRoom;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShiftManagementTest extends TestCase
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

    public function test_administrator_can_create_a_shift(): void
    {
        $response = $this->actingAs($this->admin)->post('/shifts', [
            'name' => 'Morning',
            'start_time' => '07:30',
            'is_active' => 1,
        ]);

        $response->assertRedirect('/shifts');
        $this->assertDatabaseHas('shifts', ['name' => 'Morning', 'start_time' => '07:30:00', 'is_active' => 1]);
    }

    public function test_a_shift_can_be_assigned_to_a_class(): void
    {
        $shift = Shift::factory()->create();

        $this->actingAs($this->admin)->post('/classes', [
            'name' => 'Grade 1',
            'numeric_level' => 1,
            'shift_id' => $shift->id,
            'is_active' => 1,
        ])->assertRedirect('/classes');

        $class = ClassRoom::where('name', 'Grade 1')->firstOrFail();
        $this->assertSame($shift->id, $class->shift_id);
    }

    public function test_a_shift_can_be_assigned_directly_to_a_teacher_and_an_employee(): void
    {
        $shift = Shift::factory()->create();

        $teacher = Teacher::factory()->create();
        $this->actingAs($this->admin)->put("/teachers/{$teacher->id}", array_merge(
            $teacher->only(['first_name', 'last_name', 'gender', 'phone', 'employment_type', 'status']),
            ['shift_id' => $shift->id]
        ))->assertRedirect('/teachers');
        $this->assertSame($shift->id, $teacher->fresh()->shift_id);

        $employee = Employee::factory()->create();
        [$jy, $jm, $jd] = \App\Support\JalaliDate::parts($employee->joining_date);
        $this->actingAs($this->admin)->put("/employees/{$employee->id}", array_merge(
            $employee->only(['first_name', 'last_name', 'position', 'phone', 'monthly_salary', 'status']),
            ['joining_date' => ['year' => $jy, 'month' => $jm, 'day' => $jd], 'shift_id' => $shift->id]
        ))->assertRedirect('/employees');
        $this->assertSame($shift->id, $employee->fresh()->shift_id);
    }

    public function test_a_shift_in_use_by_a_class_cannot_be_deleted(): void
    {
        $shift = Shift::factory()->create();
        ClassRoom::factory()->create(['shift_id' => $shift->id]);

        $response = $this->actingAs($this->admin)->delete("/shifts/{$shift->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_a_shift_in_use_by_a_teacher_cannot_be_deleted(): void
    {
        $shift = Shift::factory()->create();
        Teacher::factory()->create(['shift_id' => $shift->id]);

        $response = $this->actingAs($this->admin)->delete("/shifts/{$shift->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('shifts', ['id' => $shift->id]);
    }

    public function test_an_unused_shift_can_be_deleted(): void
    {
        $shift = Shift::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/shifts/{$shift->id}");

        $response->assertRedirect('/shifts');
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_a_shift_in_use_can_be_force_deleted_and_assignments_are_cleared(): void
    {
        $shift = Shift::factory()->create();
        $class = ClassRoom::factory()->create(['shift_id' => $shift->id]);
        $teacher = Teacher::factory()->create(['shift_id' => $shift->id]);
        $employee = Employee::factory()->create(['shift_id' => $shift->id]);

        $response = $this->actingAs($this->admin)->delete("/shifts/{$shift->id}/force");

        $response->assertRedirect('/shifts');
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
        // The assigned records survive - only their shift link is cleared.
        $this->assertDatabaseHas('classes', ['id' => $class->id, 'shift_id' => null]);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id, 'shift_id' => null]);
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'shift_id' => null]);
    }

    public function test_an_unused_shift_can_be_force_deleted(): void
    {
        $shift = Shift::factory()->create();

        $response = $this->actingAs($this->admin)->delete("/shifts/{$shift->id}/force");

        $response->assertRedirect('/shifts');
        $this->assertDatabaseMissing('shifts', ['id' => $shift->id]);
    }

    public function test_teacher_without_academics_permission_cannot_manage_shifts(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/shifts')->assertStatus(403);
        $this->actingAs($teacher)->post('/shifts', ['name' => 'Evening', 'start_time' => '17:00'])->assertStatus(403);
    }

    public function test_principal_can_view_but_not_manage_shifts(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->get('/shifts')->assertStatus(200);
        $this->actingAs($principal)->post('/shifts', ['name' => 'Evening', 'start_time' => '17:00'])->assertStatus(403);
    }
}
