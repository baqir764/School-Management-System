<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeInvoiceTest extends TestCase
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

    public function test_generating_invoices_creates_one_per_actively_enrolled_student_school_wide(): void
    {
        $year = AcademicYear::factory()->create();
        $students = Student::factory()->count(3)->create();

        foreach ($students as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'status' => 'active',
            ]);
        }

        $feeStructure = FeeStructure::factory()->create([
            'academic_year_id' => $year->id,
            'class_id' => null,
            'amount' => 1200,
        ]);

        $response = $this->actingAs($this->admin)->post(route('fee-invoices.store', $feeStructure));

        $response->assertRedirect(route('fee-invoices.index', ['academic_year_id' => $year->id]));
        $this->assertDatabaseCount('fee_invoices', 3);

        foreach ($students as $student) {
            $this->assertDatabaseHas('fee_invoices', [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'amount' => 1200,
            ]);
        }
    }

    public function test_generating_invoices_for_a_class_specific_fee_only_targets_that_class(): void
    {
        $year = AcademicYear::factory()->create();
        $classA = ClassRoom::factory()->create();
        $classB = ClassRoom::factory()->create();

        $studentsInA = Student::factory()->count(2)->create();
        foreach ($studentsInA as $student) {
            Enrollment::factory()->create([
                'student_id' => $student->id,
                'academic_year_id' => $year->id,
                'class_id' => $classA->id,
                'status' => 'active',
            ]);
        }

        $studentInB = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $studentInB->id,
            'academic_year_id' => $year->id,
            'class_id' => $classB->id,
            'status' => 'active',
        ]);

        $feeStructure = FeeStructure::factory()->create([
            'academic_year_id' => $year->id,
            'class_id' => $classA->id,
        ]);

        $this->actingAs($this->admin)->post(route('fee-invoices.store', $feeStructure));

        $this->assertDatabaseCount('fee_invoices', 2);
        $this->assertDatabaseMissing('fee_invoices', ['student_id' => $studentInB->id]);
    }

    public function test_re_running_generation_skips_students_who_already_have_an_invoice(): void
    {
        $year = AcademicYear::factory()->create();
        $feeStructure = FeeStructure::factory()->create(['academic_year_id' => $year->id, 'class_id' => null]);

        $alreadyInvoiced = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $alreadyInvoiced->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        FeeInvoice::factory()->create([
            'student_id' => $alreadyInvoiced->id,
            'fee_structure_id' => $feeStructure->id,
            'academic_year_id' => $year->id,
        ]);

        $newStudent = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $newStudent->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)->post(route('fee-invoices.store', $feeStructure));

        // One pre-existing + one newly created - never a duplicate for the already-invoiced student.
        $this->assertDatabaseCount('fee_invoices', 2);
        $this->assertEquals(1, FeeInvoice::query()
            ->where('fee_structure_id', $feeStructure->id)
            ->where('student_id', $alreadyInvoiced->id)
            ->count());
    }

    public function test_withdrawn_enrollments_and_other_years_are_not_invoiced(): void
    {
        $year = AcademicYear::factory()->create();
        $otherYear = AcademicYear::factory()->create();
        $feeStructure = FeeStructure::factory()->create(['academic_year_id' => $year->id, 'class_id' => null]);

        $withdrawn = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $withdrawn->id,
            'academic_year_id' => $year->id,
            'status' => 'withdrawn',
        ]);

        $wrongYear = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $wrongYear->id,
            'academic_year_id' => $otherYear->id,
            'status' => 'active',
        ]);

        $this->actingAs($this->admin)->post(route('fee-invoices.store', $feeStructure));

        $this->assertDatabaseCount('fee_invoices', 0);
    }

    public function test_dues_report_lists_only_students_with_an_outstanding_balance(): void
    {
        $year = AcademicYear::factory()->create(['is_active' => true]);

        $paidUp = Student::factory()->create(['first_name' => 'Zahra', 'last_name' => 'PaidUp']);
        $stillOwing = Student::factory()->create(['first_name' => 'Karim', 'last_name' => 'StillOwing']);

        $paidInvoice = FeeInvoice::factory()->create([
            'student_id' => $paidUp->id,
            'academic_year_id' => $year->id,
            'amount' => 500,
        ]);
        FeePayment::factory()->create(['fee_invoice_id' => $paidInvoice->id, 'amount' => 500]);

        FeeInvoice::factory()->create([
            'student_id' => $stillOwing->id,
            'academic_year_id' => $year->id,
            'amount' => 500,
        ]);

        $response = $this->actingAs($this->admin)->get(route('fee-invoices.dues', ['academic_year_id' => $year->id]));

        $response->assertStatus(200);
        $response->assertSee('StillOwing');
        $response->assertDontSee('PaidUp');
    }

    public function test_a_teacher_cannot_generate_or_view_invoices(): void
    {
        $feeStructure = FeeStructure::factory()->create();
        $teacher = User::factory()->create();
        $teacher->assignRole('Teacher');

        $this->actingAs($teacher)->get(route('fee-invoices.create', $feeStructure))->assertStatus(403);
        $this->actingAs($teacher)->get(route('fee-invoices.index'))->assertStatus(403);
    }

    public function test_a_receptionist_can_view_invoices_but_not_generate_them(): void
    {
        $feeStructure = FeeStructure::factory()->create();
        $receptionist = User::factory()->create();
        $receptionist->assignRole('Receptionist');

        $this->actingAs($receptionist)->get(route('fee-invoices.index'))->assertStatus(200);
        $this->actingAs($receptionist)->get(route('fee-invoices.create', $feeStructure))->assertStatus(403);
    }
}
