<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Mark;
use App\Models\ParentGuardian;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParentPortalTest extends TestCase
{
    use RefreshDatabase;

    protected AcademicYear $year;

    protected ClassRoom $class;

    protected Section $section;

    protected Teacher $homeroomTeacher;

    protected Teacher $subjectTeacher;

    protected Subject $subject;

    protected Student $child;

    protected User $parentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->year = AcademicYear::factory()->create(['is_active' => true]);
        $this->class = ClassRoom::factory()->create();

        $this->homeroomTeacher = Teacher::factory()->create();
        $this->section = Section::factory()->create([
            'class_id' => $this->class->id,
            'class_teacher_id' => $this->homeroomTeacher->id,
        ]);

        $this->subjectTeacher = Teacher::factory()->create();
        $this->subject = Subject::factory()->create([
            'class_id' => $this->class->id,
            'teacher_id' => $this->subjectTeacher->id,
        ]);

        $this->child = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $this->child->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'section_id' => $this->section->id,
            'status' => 'active',
        ]);

        $this->parentUser = User::factory()->create();
        $this->parentUser->assignRole('Parent');
        $guardian = ParentGuardian::factory()->create(['user_id' => $this->parentUser->id]);
        $guardian->students()->attach($this->child->id, ['relationship' => 'Father']);
    }

    public function test_parent_dashboard_shows_child_attendance_marks_and_teachers(): void
    {
        AttendanceRecord::factory()->create([
            'student_id' => $this->child->id,
            'section_id' => $this->section->id,
            'academic_year_id' => $this->year->id,
            'status' => 'present',
        ]);
        AttendanceRecord::factory()->create([
            'student_id' => $this->child->id,
            'section_id' => $this->section->id,
            'academic_year_id' => $this->year->id,
            'date' => JalaliDate::addDays(JalaliDate::today(), -1),
            'status' => 'absent',
        ]);

        $exam = Exam::factory()->create([
            'academic_year_id' => $this->year->id,
            'class_id' => $this->class->id,
            'is_published' => true,
        ]);
        $examSubject = ExamSubject::factory()->create([
            'exam_id' => $exam->id,
            'subject_id' => $this->subject->id,
            'full_marks' => 100,
            'pass_marks' => 40,
        ]);
        Mark::factory()->create([
            'exam_subject_id' => $examSubject->id,
            'student_id' => $this->child->id,
            'marks_obtained' => 80,
            'is_absent' => false,
        ]);

        $otherFamilyChild = Student::factory()->create(['first_name' => 'UnrelatedXYZ']);

        $response = $this->actingAs($this->parentUser)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertSee($this->child->fullName());
        $response->assertSee($this->subjectTeacher->fullName());
        $response->assertSee($this->homeroomTeacher->fullName());
        $response->assertSee($this->subject->name);
        $response->assertSee($exam->name);
        $response->assertSee('80');
        $response->assertSee(__('app.attendance_status_present'));
        $response->assertSee(__('app.attendance_status_absent'));
        // Another family's child must never leak into this dashboard.
        $response->assertDontSee($otherFamilyChild->fullName());
        // School-wide management figures stay hidden from pure parents.
        $response->assertDontSee(__('app.stat_active_users'));
        $response->assertDontSee(__('app.stat_pending_fees'));
    }

    public function test_admin_dashboard_has_no_parent_section(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('Super Administrator');

        $response = $this->actingAs($admin)->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertDontSee(__('app.parent_my_children'));
        $response->assertSee(__('app.stat_active_users'));
    }

    public function test_parent_can_send_message_to_own_child_teacher(): void
    {
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('Teacher');
        $this->subjectTeacher->update(['user_id' => $teacherUser->id]);

        $response = $this->actingAs($this->parentUser)->post(route('announcements.store'), [
            'title' => 'Question about homework',
            'body' => 'Please explain lesson five.',
            'teacher_ids' => [$this->subjectTeacher->id],
        ]);

        $response->assertRedirect();
        $announcement = Announcement::query()->firstOrFail();
        $this->assertTrue($announcement->is_published);
        $this->assertEquals(['Teacher'], $announcement->target_roles);
        $this->assertEquals([$this->subjectTeacher->id], array_map('intval', (array) $announcement->target_user_ids));
        $this->assertDatabaseHas('notifications', [
            'notifiable_type' => User::class,
            'notifiable_id' => $teacherUser->id,
        ]);
    }

    public function test_parent_cannot_message_a_teacher_of_no_child_of_theirs(): void
    {
        $stranger = Teacher::factory()->create();

        $response = $this->actingAs($this->parentUser)->post(route('announcements.store'), [
            'title' => 'Hello',
            'body' => 'Trying to reach anyone.',
            'teacher_ids' => [$stranger->id],
        ]);

        $response->assertSessionHasErrors('teacher_ids');
        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_parent_cannot_broadcast_audience_is_forced_to_teachers(): void
    {
        $response = $this->actingAs($this->parentUser)->post(route('announcements.store'), [
            'title' => 'Broadcast attempt',
            'body' => 'Trying to reach every parent.',
            'teacher_ids' => [$this->subjectTeacher->id],
            'target_roles' => ['Parent', 'Student'],
        ]);

        $response->assertRedirect();
        $announcement = Announcement::query()->firstOrFail();
        $this->assertEquals(['Teacher'], $announcement->target_roles);
    }

    public function test_addressed_teacher_sees_message_but_unrelated_teacher_does_not(): void
    {
        $teacherUser = User::factory()->create();
        $teacherUser->assignRole('Teacher');
        $this->subjectTeacher->update(['user_id' => $teacherUser->id]);

        $otherTeacher = Teacher::factory()->create();
        $otherUser = User::factory()->create();
        $otherUser->assignRole('Teacher');
        $otherTeacher->update(['user_id' => $otherUser->id]);

        $this->actingAs($this->parentUser)->post(route('announcements.store'), [
            'title' => 'PrivateQuestionXYZ',
            'body' => 'Only for my child teacher.',
            'teacher_ids' => [$this->subjectTeacher->id],
        ])->assertRedirect();

        $this->actingAs($teacherUser)->get(route('announcements.index'))
            ->assertStatus(200)->assertSee('PrivateQuestionXYZ');

        $this->actingAs($otherUser)->get(route('announcements.index'))
            ->assertStatus(200)->assertDontSee('PrivateQuestionXYZ');
    }

    public function test_parent_cannot_edit_another_parents_message(): void
    {
        $otherParent = User::factory()->create();
        $otherParent->assignRole('Parent');
        $otherGuardian = ParentGuardian::factory()->create(['user_id' => $otherParent->id]);
        $otherGuardian->students()->attach($this->child->id, ['relationship' => 'Mother']);

        $this->actingAs($this->parentUser)->post(route('announcements.store'), [
            'title' => 'MineXYZ',
            'body' => 'My message.',
            'teacher_ids' => [$this->subjectTeacher->id],
        ])->assertRedirect();

        $announcement = Announcement::query()->firstOrFail();

        // Same teacher is valid for both parents, so only ownership blocks this.
        $this->actingAs($otherParent)->put(route('announcements.update', $announcement), [
            'title' => 'Hijacked',
            'body' => 'Hijacked body.',
            'teacher_ids' => [$this->subjectTeacher->id],
        ])->assertStatus(403);

        $this->actingAs($otherParent)->delete(route('announcements.destroy', $announcement))
            ->assertStatus(403);
    }

    public function test_parent_can_edit_own_message(): void
    {
        $this->actingAs($this->parentUser)->post(route('announcements.store'), [
            'title' => 'OriginalXYZ',
            'body' => 'Original body.',
            'teacher_ids' => [$this->subjectTeacher->id],
        ])->assertRedirect();

        $announcement = Announcement::query()->firstOrFail();

        $this->actingAs($this->parentUser)->put(route('announcements.update', $announcement), [
            'title' => 'EditedXYZ',
            'body' => 'Edited body.',
            'teacher_ids' => [$this->subjectTeacher->id],
        ])->assertRedirect();

        $this->assertEquals('EditedXYZ', $announcement->refresh()->title);
    }

    public function test_parent_without_send_permission_cannot_compose(): void
    {
        $studentUser = User::factory()->create();
        $studentUser->assignRole('Student');

        $this->actingAs($studentUser)->get(route('announcements.create'))->assertStatus(403);
        $this->actingAs($studentUser)->post(route('announcements.store'), [
            'title' => 'Nope',
            'body' => 'Nope.',
            'teacher_ids' => [$this->subjectTeacher->id],
        ])->assertStatus(403);
    }
}
