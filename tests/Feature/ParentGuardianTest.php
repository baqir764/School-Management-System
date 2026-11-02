<?php

namespace Tests\Feature;

use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentGuardianTest extends TestCase
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

    public function test_administrator_can_create_a_parent(): void
    {
        $response = $this->actingAs($this->admin)->post('/parents', [
            'first_name' => 'Homayoon',
            'last_name' => 'Sadiqi',
            'phone' => '0700000000',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('parents', ['first_name' => 'Homayoon', 'last_name' => 'Sadiqi']);
    }

    public function test_a_parent_can_be_linked_to_multiple_students(): void
    {
        $parent = ParentGuardian::factory()->create();
        $studentOne = Student::factory()->create();
        $studentTwo = Student::factory()->create();

        $this->actingAs($this->admin)->post("/parents/{$parent->id}/link-student", [
            'student_id' => $studentOne->id,
            'relationship' => 'Father',
            'is_primary_contact' => 1,
        ])->assertStatus(302);

        $this->actingAs($this->admin)->post("/parents/{$parent->id}/link-student", [
            'student_id' => $studentTwo->id,
            'relationship' => 'Father',
        ])->assertStatus(302);

        $this->assertCount(2, $parent->fresh()->students);
    }

    public function test_unlinking_a_student_removes_the_pivot_row_only(): void
    {
        $parent = ParentGuardian::factory()->create();
        $student = Student::factory()->create();
        $parent->students()->attach($student->id, ['relationship' => 'Mother']);

        $this->actingAs($this->admin)->delete("/parents/{$parent->id}/unlink-student/{$student->id}")
            ->assertStatus(302);

        $this->assertCount(0, $parent->fresh()->students);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_receptionist_can_manage_parents(): void
    {
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->get('/parents')->assertStatus(200);
        $this->actingAs($receptionist)->post('/parents', [
            'first_name' => 'Front', 'last_name' => 'Desk', 'phone' => '0700000001',
        ])->assertStatus(302);
    }
}
