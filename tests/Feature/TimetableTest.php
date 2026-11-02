<?php

namespace Tests\Feature;

use App\Models\Period;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimetableTest extends TestCase
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

    public function test_administrator_can_create_a_period(): void
    {
        $response = $this->actingAs($this->admin)->post('/periods', [
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'sort_order' => 1,
        ]);

        $response->assertRedirect('/periods');
        $this->assertDatabaseHas('periods', ['name' => 'Period 1']);
    }

    public function test_administrator_can_assign_a_subject_to_a_timetable_slot(): void
    {
        $section = Section::factory()->create();
        $period = Period::factory()->create();
        $subject = Subject::factory()->create(['class_id' => $section->class_id]);
        $teacher = Teacher::factory()->create();

        $response = $this->actingAs($this->admin)->post('/timetable-entries', [
            'section_id' => $section->id,
            'period_id' => $period->id,
            'day_of_week' => 6,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('timetable_entries', [
            'section_id' => $section->id,
            'period_id' => $period->id,
            'day_of_week' => 6,
        ]);
    }

    public function test_a_slot_already_taken_cannot_be_assigned_again(): void
    {
        $entry = TimetableEntry::factory()->create(['day_of_week' => 6]);
        $otherSubject = Subject::factory()->create();

        $response = $this->actingAs($this->admin)->post('/timetable-entries', [
            'section_id' => $entry->section_id,
            'period_id' => $entry->period_id,
            'day_of_week' => 6,
            'subject_id' => $otherSubject->id,
            'teacher_id' => $entry->teacher_id,
        ]);

        $response->assertSessionHasErrors('section_id');
    }

    public function test_a_teacher_cannot_be_double_booked_in_the_same_period(): void
    {
        $entry = TimetableEntry::factory()->create(['day_of_week' => 6]);
        $otherSection = Section::factory()->create();
        $subject = Subject::factory()->create(['class_id' => $otherSection->class_id]);

        $response = $this->actingAs($this->admin)->post('/timetable-entries', [
            'section_id' => $otherSection->id,
            'period_id' => $entry->period_id,
            'day_of_week' => 6,
            'subject_id' => $subject->id,
            'teacher_id' => $entry->teacher_id,
        ]);

        $response->assertSessionHasErrors('teacher_id');
    }

    public function test_a_teacher_without_timetable_manage_cannot_create_a_period(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->post('/periods', [
            'name' => 'Period 1',
            'start_time' => '08:00',
            'end_time' => '08:45',
            'sort_order' => 1,
        ])->assertStatus(403);
    }

    public function test_a_teacher_can_view_the_timetable_grid(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get('/timetable')->assertStatus(200);
    }
}
