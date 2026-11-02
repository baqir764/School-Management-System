<?php

namespace Tests\Feature;

use App\Models\Teacher;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TeacherManagementTest extends TestCase
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

    public function test_administrator_can_create_a_teacher(): void
    {
        $response = $this->actingAs($this->admin)->post('/teachers', [
            'first_name' => 'Aziz',
            'last_name' => 'Rahimi',
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertRedirect('/teachers');
        $this->assertDatabaseHas('teachers', ['first_name' => 'Aziz', 'last_name' => 'Rahimi']);

        $teacher = Teacher::where('first_name', 'Aziz')->firstOrFail();
        $this->assertNotEmpty($teacher->employee_code);
    }

    public function test_a_user_account_can_only_be_linked_to_one_teacher(): void
    {
        $account = User::factory()->create();
        Teacher::factory()->create(['user_id' => $account->id]);

        $response = $this->actingAs($this->admin)->post('/teachers', [
            'first_name' => 'Second',
            'last_name' => 'Link',
            'user_id' => $account->id,
            'employment_type' => 'full_time',
            'status' => 'active',
        ]);

        $response->assertSessionHasErrors('user_id');
    }

    public function test_a_teacher_assigned_to_a_section_cannot_be_deleted(): void
    {
        $teacher = Teacher::factory()->create();
        $class = \App\Models\ClassRoom::factory()->create();
        \App\Models\Section::factory()->create(['class_id' => $class->id, 'class_teacher_id' => $teacher->id]);

        $response = $this->actingAs($this->admin)->delete("/teachers/{$teacher->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('teachers', ['id' => $teacher->id]);
    }

    public function test_receptionist_cannot_manage_teachers(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->get('/teachers')->assertStatus(403);
    }

    public function test_administrator_can_print_a_teacher_id_card(): void
    {
        $teacher = Teacher::factory()->create();

        $response = $this->actingAs($this->admin)->get(route('teachers.id-card', $teacher));

        $response->assertOk();
        // The back side carries the scannable staff payload.
        $this->assertStringContainsString('&quot;t&quot;:&quot;teacher&quot;', $response->getContent());
        $this->assertStringContainsString($teacher->employee_code, $response->getContent());
    }
}
