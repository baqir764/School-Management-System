<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentManagementTest extends TestCase
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

    public function test_registering_a_student_generates_an_admission_number(): void
    {
        $response = $this->actingAs($this->admin)->post('/students', [
            'first_name' => 'Ahmad',
            'last_name' => 'Karimi',
            'gender' => 'male',
            'admission_date' => $this->todayJalaliParts(),
            'status' => 'active',
        ]);

        $student = Student::where('first_name', 'Ahmad')->firstOrFail();
        $response->assertRedirect("/students/{$student->id}");
        $this->assertStringStartsWith('STU-'.JalaliDate::parts(JalaliDate::today())[0].'-', $student->admission_number);
    }

    public function test_admission_numbers_increment_sequentially_within_a_year(): void
    {
        $this->actingAs($this->admin)->post('/students', [
            'first_name' => 'First', 'last_name' => 'Student', 'gender' => 'male',
            'admission_date' => $this->todayJalaliParts(), 'status' => 'active',
        ]);
        $this->actingAs($this->admin)->post('/students', [
            'first_name' => 'Second', 'last_name' => 'Student', 'gender' => 'female',
            'admission_date' => $this->todayJalaliParts(), 'status' => 'active',
        ]);

        $first = Student::where('first_name', 'First')->firstOrFail();
        $second = Student::where('first_name', 'Second')->firstOrFail();

        $this->assertNotEquals($first->admission_number, $second->admission_number);
    }

    /**
     * The <x-jalali-date> component submits day/month/year as an array -
     * every test posting to a form with a Shamsi date field needs this
     * shape instead of a single "Y-m-d" string.
     */
    private function todayJalaliParts(): array
    {
        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    public function test_a_student_can_be_enrolled_into_a_section_for_an_academic_year(): void
    {
        $student = Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id]);

        $response = $this->actingAs($this->admin)->post("/students/{$student->id}/enroll", [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'roll_number' => '1',
            'enrollment_date' => $this->todayJalaliParts(),
        ]);

        $response->assertRedirect("/students/{$student->id}");
        $this->assertDatabaseHas('enrollments', ['student_id' => $student->id, 'academic_year_id' => $year->id]);
    }

    public function test_a_student_cannot_be_enrolled_twice_in_the_same_academic_year(): void
    {
        $student = Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();
        $sectionOne = Section::factory()->create(['class_id' => $class->id]);
        $sectionTwo = Section::factory()->create(['class_id' => $class->id]);

        $student->enrollments()->create([
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $sectionOne->id,
            'enrollment_date' => JalaliDate::today(),
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post("/students/{$student->id}/enroll", [
            'academic_year_id' => $year->id,
            'class_id' => $class->id,
            'section_id' => $sectionTwo->id,
            'enrollment_date' => $this->todayJalaliParts(),
        ]);

        $response->assertSessionHasErrors('academic_year_id');
    }

    public function test_a_student_with_enrollment_history_cannot_be_deleted(): void
    {
        $student = Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student->enrollments()->create([
            'academic_year_id' => $year->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'enrollment_date' => JalaliDate::today(), 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete("/students/{$student->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    public function test_admission_number_uses_the_active_academic_year_start_date_year(): void
    {
        AcademicYear::factory()->create([
            'start_date' => '1405-01-01',
            'end_date' => '1405-12-29',
            'is_active' => true,
        ]);

        $this->actingAs($this->admin)->post('/students', [
            'first_name' => 'Ahmad',
            'last_name' => 'Karimi',
            'gender' => 'male',
            'admission_date' => $this->todayJalaliParts(),
            'status' => 'active',
        ]);

        $student = Student::where('first_name', 'Ahmad')->firstOrFail();
        $this->assertStringStartsWith('STU-1405-', $student->admission_number);
    }

    public function test_an_enrollment_is_permanently_removed(): void
    {
        $student = Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id]);
        $enrollment = $student->enrollments()->create([
            'academic_year_id' => $year->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'enrollment_date' => JalaliDate::today(), 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete("/students/{$student->id}/enroll/{$enrollment->id}");

        $response->assertRedirect();
        // Gone from the database entirely - not merely soft-deleted - so
        // the student is fully freed from that academic year and section.
        $this->assertDatabaseMissing('enrollments', ['id' => $enrollment->id]);
    }

    public function test_a_student_can_be_transferred_to_another_class_and_section(): void
    {
        $student = Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $classOne = ClassRoom::factory()->create();
        $sectionOne = Section::factory()->create(['class_id' => $classOne->id]);
        $classTwo = ClassRoom::factory()->create();
        $sectionTwo = Section::factory()->create(['class_id' => $classTwo->id]);
        $enrollment = $student->enrollments()->create([
            'academic_year_id' => $year->id, 'class_id' => $classOne->id, 'section_id' => $sectionOne->id,
            'enrollment_date' => JalaliDate::today(), 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put("/students/{$student->id}/enroll/{$enrollment->id}", [
            'class_id' => $classTwo->id,
            'section_id' => $sectionTwo->id,
            'roll_number' => '5',
        ]);

        $response->assertRedirect("/students/{$student->id}");
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'academic_year_id' => $year->id,
            'class_id' => $classTwo->id,
            'section_id' => $sectionTwo->id,
            'roll_number' => '5',
        ]);
    }

    public function test_a_transfer_rejects_a_section_of_another_class(): void
    {
        $student = Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $classOne = ClassRoom::factory()->create();
        $sectionOne = Section::factory()->create(['class_id' => $classOne->id]);
        $classTwo = ClassRoom::factory()->create();
        $enrollment = $student->enrollments()->create([
            'academic_year_id' => $year->id, 'class_id' => $classOne->id, 'section_id' => $sectionOne->id,
            'enrollment_date' => JalaliDate::today(), 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put("/students/{$student->id}/enroll/{$enrollment->id}", [
            'class_id' => $classTwo->id,
            'section_id' => $sectionOne->id,
        ]);

        $response->assertSessionHasErrors('section_id');
    }

    public function test_a_deleted_student_can_be_restored_from_the_trash(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->admin)->delete("/students/{$student->id}");
        $this->assertSoftDeleted('students', ['id' => $student->id]);

        $response = $this->actingAs($this->admin)->patch("/students/{$student->id}/restore");

        $response->assertRedirect('/students');
        $this->assertTrue(Student::query()->where('id', $student->id)->exists());
    }

    public function test_a_student_can_be_permanently_deleted_with_their_history(): void
    {
        $student = Student::factory()->create();
        $year = AcademicYear::factory()->create();
        $class = ClassRoom::factory()->create();
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student->enrollments()->create([
            'academic_year_id' => $year->id, 'class_id' => $class->id, 'section_id' => $section->id,
            'enrollment_date' => JalaliDate::today(), 'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->delete("/students/{$student->id}/force");

        $response->assertRedirect('/students');
        $this->assertDatabaseMissing('students', ['id' => $student->id]);
        $this->assertDatabaseMissing('enrollments', ['student_id' => $student->id]);
    }

    public function test_administrator_can_upload_and_download_a_student_document(): void
    {
        \Illuminate\Support\Facades\Storage::fake('secure_documents');
        $student = Student::factory()->create();

        $response = $this->actingAs($this->admin)->post("/students/{$student->id}/documents", [
            'document_type' => 'birth_certificate',
            'title' => 'Birth Certificate',
            'file' => UploadedFile::fake()->create('birth-certificate.pdf', 100, 'application/pdf'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('student_documents', ['student_id' => $student->id, 'title' => 'Birth Certificate']);

        $document = $student->documents()->firstOrFail();
        \Illuminate\Support\Facades\Storage::disk('secure_documents')->assertExists($document->file_path);

        $this->actingAs($this->admin)
            ->get("/students/{$student->id}/documents/{$document->id}/download")
            ->assertStatus(200);
    }

    public function test_a_role_without_students_view_cannot_see_student_documents_route(): void
    {
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');
        $student = Student::factory()->create();

        $this->actingAs($teacher)->get('/students')->assertStatus(403);
    }
}
