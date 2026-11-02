<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Announcement;
use App\Models\AttendanceRecord;
use App\Models\ClassRoom;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\GradeScale;
use App\Models\IncomeCategory;
use App\Models\MiscIncome;
use App\Models\ParentGuardian;
use App\Models\Period;
use App\Models\SalaryPayment;
use App\Models\Section;
use App\Models\Shift;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Renders every authenticated GET route once against the fully seeded demo
 * dataset (DatabaseSeeder + TestDataSeeder). The goal is not to re-test the
 * controllers' logic - the module suites do that - but to prove each screen
 * actually renders with realistic data and none throw a 500.
 */
class SmokeRoutesTest extends TestCase
{
    use RefreshDatabase;

    private const SKIP = [
        'login', 'logout', 'password.request', 'password.email', 'password.reset',
        'password.store', 'locale.update', 'ignition.executeSolution',
        'ignition.healthCheck', 'ignition.updateConfig', 'sanctum.csrf-cookie',
        'storage.local', 'auth.token', 'up',
    ];

    /** @var array<string, string> parameter name => model class */
    private const PARAM_MODELS = [
        'academic_year' => AcademicYear::class,
        'class' => ClassRoom::class,
        'section' => Section::class,
        'shift' => Shift::class,
        'subject' => Subject::class,
        'teacher' => Teacher::class,
        'student' => Student::class,
        'employee' => Employee::class,
        'exam' => Exam::class,
        'exam_subject' => ExamSubject::class,
        'fee_invoice' => FeeInvoice::class,
        'fee_structure' => FeeStructure::class,
        'fee_payment' => FeePayment::class,
        'period' => Period::class,
        'salary_payment' => SalaryPayment::class,
        'timetable_entry' => TimetableEntry::class,
        'grade_scale' => GradeScale::class,
        'user' => User::class,
        'announcement' => Announcement::class,
        'parent' => ParentGuardian::class,
        'record' => AttendanceRecord::class,
        'document' => StudentDocument::class,
        'expense_category' => ExpenseCategory::class,
        'income_category' => IncomeCategory::class,
        'expense' => Expense::class,
        'misc_income' => MiscIncome::class,
        'enrollment' => Enrollment::class,
    ];

    public function test_every_module_route_renders_for_super_admin(): void
    {
        $this->seed();
        (new TestDataSeeder())->run();

        // The shifts screens need at least one row and no seeder makes one.
        if (Shift::query()->count() === 0) {
            Shift::factory()->create();
        }

        $admin = User::query()->where('email', 'admin@school.local')->firstOrFail();

        $skippedRequests = [];

        $this->actingAs($admin);

        $request = $this->app->make('request');
        $request->setUserResolver(fn () => $admin);

        foreach ($this->app['router']->getRoutes() as $route) {
            $name = (string) $route->getName();

            if (! $name || ! in_array('GET', $route->methods(), true)) {
                continue;
            }

            if ($this->shouldSkip($name)) {
                continue;
            }

            $url = $this->buildUrl($name, $route->uri(), $route->parameterNames());

            try {
                $response = $this->get($url);
            } catch (\Throwable $e) {
                $this->fail("GET {$name} [{$url}] threw: {$e->getMessage()}");
            }

            $status = $response->getStatusCode();

            if ($status !== 200) {
                $skippedRequests[$name] = "{$status} <- {$url}";
            }
        }

        $this->assertSame([], $skippedRequests, 'Non-200 responses on admin GET routes: '.json_encode($skippedRequests));
    }

    public function test_parent_dashboard_and_announcements_render_for_linked_parent(): void
    {
        $this->seed();
        (new TestDataSeeder())->run();

        $parent = User::query()->where('email', 'parent.demo0@aaiwo.local')->firstOrFail();

        $this->actingAs($parent);

        $this->get(route('dashboard'))->assertSuccessful();
        $this->get(route('announcements.index'))->assertSuccessful();
        $this->get(route('announcements.create'))->assertSuccessful();

        $message = Announcement::query()->where('target_user_ids', '!=', 'null')->latest()->firstOrFail();

        $this->get(route('announcements.show', $message))->assertSuccessful();
        $this->get(route('announcements.edit', $message))->assertSuccessful();
    }

    public function test_teacher_dashboard_renders_for_linked_teacher(): void
    {
        $this->seed();
        (new TestDataSeeder())->run();

        $teacher = User::query()->where('email', 'teacher.demo0@aaiwo.local')->firstOrFail();

        $this->actingAs($teacher);

        $this->get(route('dashboard'))->assertSuccessful();
    }

    private function shouldSkip(string $name): bool
    {
        // Cached route tables rewrite unnamed routes (like the "/" redirect)
        // into "generated::<hash>" names - never something to smoke-test.
        if (str_starts_with($name, 'generated::')) {
            return true;
        }

        foreach (self::SKIP as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix.'.')) {
                return true;
            }
        }

        return false;
    }

    private function buildUrl(string $name, string $uri, array $params): string
    {
        $query = [];

        // The transfer screen is nested (student + their enrollment): the
        // generic first-of-each-table substitution below would pair a
        // random student with a random enrollment and correctly 404, so
        // resolve both ids from one real enrollment instead.
        if ($name === 'students.enroll.edit') {
            $enrollment = Enrollment::query()->firstOrFail();
            $uri = str_replace('{student}', (string) $enrollment->student_id, $uri);
            $uri = str_replace('{enrollment}', (string) $enrollment->id, $uri);

            return $uri;
        }

        foreach ($params as $param) {
            $model = self::PARAM_MODELS[$param] ?? null;

            if (! $model) {
                $this->fail("Unknown route parameter '{$param}' for URI {$uri}");
            }

            $uri = str_replace('{'.$param.'}', (string) $model::query()->firstOrFail()->id, $uri);
        }

        // Context-dependent create pages are reached from a parent screen
        // with pre-selected rows/columns; open them the way the UI does.
        if ($name === 'timetable-entries.create') {
            $query['section_id'] = Section::query()->firstOrFail()->id;
            $query['period_id'] = Period::query()->firstOrFail()->id;
            $query['day_of_week'] = 1;
        }

        if ($name === 'salary-payments.create') {
            $query['payee_type'] = 'teacher';
            $query['payee_id'] = Teacher::query()->firstOrFail()->id;
        }

        // print-many expects at least one selected invoice to render.
        if ($name === 'fee-invoices.print-many') {
            $query['ids'] = [FeeInvoice::query()->firstOrFail()->id];
        }

        return count($query) ? $uri.'?'.http_build_query($query) : $uri;
    }
}