<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AcademicStructureTest extends TestCase
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

    public function test_administrator_can_create_an_academic_year(): void
    {
        $response = $this->actingAs($this->admin)->post('/academic-years', [
            'name' => '1404-1405',
            'start_date' => ['year' => 1404, 'month' => 6, 'day' => 1],
            'end_date' => ['year' => 1405, 'month' => 3, 'day' => 15],
        ]);

        $response->assertRedirect('/academic-years');
        $this->assertDatabaseHas('academic_years', ['name' => '1404-1405', 'start_date' => '1404-06-01', 'end_date' => '1405-03-15']);
    }

    public function test_activating_an_academic_year_deactivates_every_other_year(): void
    {
        $yearOne = AcademicYear::factory()->create(['is_active' => true]);
        $yearTwo = AcademicYear::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin)->patch("/academic-years/{$yearTwo->id}/activate");

        $this->assertFalse($yearOne->fresh()->is_active);
        $this->assertTrue($yearTwo->fresh()->is_active);
    }

    public function test_academic_year_with_enrollments_cannot_be_deleted(): void
    {
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student = \App\Models\Student::factory()->create();
        $student->enrollments()->create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'enrollment_date' => JalaliDate::today(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete("/academic-years/{$year->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    public function test_administrator_can_create_a_class_and_a_section_within_it(): void
    {
        $this->actingAs($this->admin)->post('/classes', [
            'name' => 'Grade 1',
            'numeric_level' => 1,
            'is_active' => 1,
        ])->assertRedirect('/classes');

        $class = ClassRoom::where('name', 'Grade 1')->firstOrFail();

        $teacher = Teacher::factory()->create();

        $this->actingAs($this->admin)->post('/sections', [
            'class_id' => $class->id,
            'name' => 'A',
            'class_teacher_id' => $teacher->id,
            'capacity' => 30,
        ])->assertRedirect('/sections');

        $this->assertDatabaseHas('sections', ['class_id' => $class->id, 'name' => 'A']);
    }

    public function test_a_class_numeric_level_can_be_reused_after_the_original_is_deleted(): void
    {
        $class = ClassRoom::factory()->create(['numeric_level' => 6]);
        $this->actingAs($this->admin)->delete("/classes/{$class->id}")->assertRedirect('/classes');

        $response = $this->actingAs($this->admin)->post('/classes', [
            'name' => 'Grade 6',
            'numeric_level' => 6,
            'is_active' => 1,
        ]);

        $response->assertRedirect('/classes')->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('classes', ['numeric_level' => 6, 'name' => 'Grade 6']);
        // Deletion is permanent now (see the drop_soft_deletes migration) -
        // there should be exactly one row at this level, not a live one
        // plus a trashed leftover.
        $this->assertDatabaseCount('classes', 1);
    }

    public function test_a_class_with_a_fee_structure_cannot_be_deleted(): void
    {
        $class = ClassRoom::factory()->create();
        \App\Models\FeeStructure::factory()->create(['class_id' => $class->id]);

        $response = $this->actingAs($this->admin)->delete("/classes/{$class->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_two_sections_in_the_same_class_cannot_share_a_name(): void
    {
        $class = ClassRoom::factory()->create();
        Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);

        $response = $this->actingAs($this->admin)->post('/sections', [
            'class_id' => $class->id,
            'name' => 'A',
        ]);

        $response->assertSessionHasErrors('name');
    }

    public function test_a_section_name_can_be_reused_after_the_original_is_deleted(): void
    {
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id, 'name' => 'A']);
        $this->actingAs($this->admin)->delete("/sections/{$section->id}")->assertRedirect('/sections');

        $response = $this->actingAs($this->admin)->post('/sections', [
            'class_id' => $class->id,
            'name' => 'A',
            'capacity' => 30,
        ]);

        $response->assertRedirect('/sections')->assertSessionDoesntHaveErrors();
        $this->assertDatabaseHas('sections', ['class_id' => $class->id, 'name' => 'A', 'capacity' => 30]);
        // Deletion is permanent now (see the drop_soft_deletes migration) -
        // there should be exactly one row for this class/name, not a live
        // one plus a trashed leftover.
        $this->assertDatabaseCount('sections', 1);
    }

    public function test_a_section_with_timetable_entries_cannot_be_deleted(): void
    {
        $section = Section::factory()->create();
        \App\Models\TimetableEntry::factory()->create(['section_id' => $section->id]);

        $response = $this->actingAs($this->admin)->delete("/sections/{$section->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('sections', ['id' => $section->id]);
    }

    public function test_a_class_with_sections_cannot_be_deleted(): void
    {
        $class = ClassRoom::factory()->create();
        Section::factory()->create(['class_id' => $class->id]);

        $response = $this->actingAs($this->admin)->delete("/classes/{$class->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_a_class_with_exams_cannot_be_deleted(): void
    {
        $class = ClassRoom::factory()->create();
        \App\Models\Exam::factory()->create(['class_id' => $class->id]);

        $response = $this->actingAs($this->admin)->delete("/classes/{$class->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('classes', ['id' => $class->id]);
    }

    public function test_a_class_in_use_can_be_force_deleted_with_everything_under_it(): void
    {
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id]);

        $student = \App\Models\Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $student->enrollments()->create([
            'academic_year_id' => $year->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'enrollment_date' => JalaliDate::today(), 'status' => 'active',
        ]);

        $exam = \App\Models\Exam::factory()->create(['class_id' => $class->id]);
        $structure = \App\Models\FeeStructure::factory()->create(['class_id' => $class->id]);
        $subject = Subject::factory()->create(['class_id' => $class->id]);

        $response = $this->actingAs($this->admin)->delete("/classes/{$class->id}/force");

        $response->assertRedirect('/classes');
        $this->assertDatabaseMissing('classes', ['id' => $class->id]);
        $this->assertDatabaseMissing('sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id, 'academic_year_id' => $year->id]);
        $this->assertDatabaseMissing('exams', ['id' => $exam->id]);
        $this->assertDatabaseMissing('fee_structures', ['id' => $structure->id]);
        // Subjects are unlinked from the class, not deleted.
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'class_id' => null]);
    }

    public function test_administrator_can_create_a_subject(): void
    {
        $class = ClassRoom::factory()->create();

        $response = $this->actingAs($this->admin)->post('/subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH-1',
            'class_id' => $class->id,
            'full_marks' => 100,
            'pass_marks' => 40,
            'is_active' => 1,
        ]);

        $response->assertRedirect('/subjects');
        $this->assertDatabaseHas('subjects', ['code' => 'MATH-1']);
    }

    public function test_pass_marks_cannot_exceed_full_marks(): void
    {
        $response = $this->actingAs($this->admin)->post('/subjects', [
            'name' => 'Mathematics',
            'code' => 'MATH-1',
            'full_marks' => 50,
            'pass_marks' => 60,
        ]);

        $response->assertSessionHasErrors('pass_marks');
    }

    public function test_teacher_without_academics_permission_cannot_manage_classes(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/classes')->assertStatus(403);
        $this->actingAs($teacher)->post('/classes', ['name' => 'Grade 2', 'numeric_level' => 2])->assertStatus(403);
    }

    public function test_principal_can_view_but_not_manage_academics(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->get('/classes')->assertStatus(200);
        $this->actingAs($principal)->post('/classes', ['name' => 'Grade 2', 'numeric_level' => 2])->assertStatus(403);
    }
}
