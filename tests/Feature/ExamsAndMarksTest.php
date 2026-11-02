<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeScale;
use App\Models\Section;
use App\Models\Student;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamsAndMarksTest extends TestCase
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

    public function test_administrator_can_create_an_exam(): void
    {
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();

        $today = JalaliDate::today();
        [$startYear, $startMonth, $startDay] = JalaliDate::parts(JalaliDate::addDays($today, 7));
        [$endYear, $endMonth, $endDay] = JalaliDate::parts(JalaliDate::addDays($today, 14));

        $response = $this->actingAs($this->admin)->post('/exams', [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'name' => 'Mid-Term Exam',
            'term_type' => 'mid_term',
            'start_date' => ['year' => $startYear, 'month' => $startMonth, 'day' => $startDay],
            'end_date' => ['year' => $endYear, 'month' => $endMonth, 'day' => $endDay],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('exams', ['name' => 'Mid-Term Exam']);
    }

    public function test_administrator_can_attach_a_subject_to_an_exam(): void
    {
        $exam = Exam::factory()->create();
        $subject = Subject::factory()->create(['class_id' => $exam->class_id]);

        $response = $this->actingAs($this->admin)->post("/exams/{$exam->id}/subjects", [
            'subject_id' => $subject->id,
            'full_marks' => 100,
            'pass_marks' => 40,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('exam_subjects', ['exam_id' => $exam->id, 'subject_id' => $subject->id]);
    }

    public function test_the_same_subject_cannot_be_attached_to_an_exam_twice(): void
    {
        $examSubject = ExamSubject::factory()->create();

        $response = $this->actingAs($this->admin)->post("/exams/{$examSubject->exam_id}/subjects", [
            'subject_id' => $examSubject->subject_id,
            'full_marks' => 100,
            'pass_marks' => 40,
        ]);

        $response->assertSessionHasErrors('subject_id');
    }

    public function test_administrator_can_toggle_exam_publish_state(): void
    {
        $exam = Exam::factory()->create(['is_published' => false]);

        $this->actingAs($this->admin)->patch("/exams/{$exam->id}/toggle-publish")->assertRedirect();

        $this->assertTrue($exam->fresh()->is_published);
    }

    public function test_grade_bands_cannot_overlap(): void
    {
        GradeScale::factory()->create(['min_percentage' => 80, 'max_percentage' => 100]);

        $response = $this->actingAs($this->admin)->post('/grade-scales', [
            'grade_name' => 'A',
            'min_percentage' => 70,
            'max_percentage' => 85,
            'sort_order' => 1,
        ]);

        $response->assertSessionHasErrors('min_percentage');
    }

    public function test_a_subject_teacher_can_enter_marks_for_their_own_subject(): void
    {
        [$examSubject, $student, $account] = $this->buildGradableExamSubject();

        $response = $this->actingAs($account)->post("/marks/{$examSubject->id}", [
            'marks' => [$student->id => 85],
            'absent' => [],
            'remarks' => [],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('marks', [
            'exam_subject_id' => $examSubject->id,
            'student_id' => $student->id,
            'marks_obtained' => 85,
        ]);
    }

    public function test_a_teacher_cannot_enter_marks_for_a_subject_they_do_not_teach(): void
    {
        [$examSubject, $student] = $this->buildGradableExamSubject();

        $otherAccount = User::factory()->create();
        $otherAccount->assignRole('Teacher');
        Teacher::factory()->create(['user_id' => $otherAccount->id]);

        $response = $this->actingAs($otherAccount)->post("/marks/{$examSubject->id}", [
            'marks' => [$student->id => 85],
        ]);

        $response->assertSessionHasErrors('marks');
    }

    public function test_marks_obtained_cannot_exceed_full_marks(): void
    {
        [$examSubject, $student, $account] = $this->buildGradableExamSubject();

        $response = $this->actingAs($account)->post("/marks/{$examSubject->id}", [
            'marks' => [$student->id => 999],
        ]);

        $response->assertSessionHasErrors("marks.{$student->id}");
    }

    /**
     * @return array{0: ExamSubject, 1: Student, 2: User}
     */
    private function buildGradableExamSubject(): array
    {
        $year = AcademicYear::factory()->create(['is_active' => true]);
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id]);

        $account = User::factory()->create();
        $account->assignRole('Teacher');
        $teacher = Teacher::factory()->create(['user_id' => $account->id]);

        $subject = Subject::factory()->create(['class_id' => $class->id, 'teacher_id' => $teacher->id]);
        $exam = Exam::factory()->create(['academic_year_id' => $year->id, 'class_id' => $class->id]);
        $examSubject = ExamSubject::factory()->create(['exam_id' => $exam->id, 'subject_id' => $subject->id, 'full_marks' => 100, 'pass_marks' => 40]);

        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);

        return [$examSubject, $student, $account];
    }
}
