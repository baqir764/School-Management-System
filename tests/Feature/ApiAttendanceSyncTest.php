<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Token API backing the offline attendance APK: POST /api/login issues a
 * Sanctum token, POST /api/attendance/sync stores a batch of offline rows
 * under the same rules as the web Excel import.
 */
class ApiAttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    protected User $clerk;

    protected AcademicYear $year;

    protected Section $section;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->clerk = User::factory()->create();
        $this->clerk->assignRole('Attendance Clerk');

        $this->year = AcademicYear::factory()->create(['is_active' => true]);
        $class = ClassRoom::factory()->create();
        $this->section = Section::factory()->create(['class_id' => $class->id]);
        $this->student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $class->id,
            'section_id' => $this->section->id,
            'status' => 'active',
        ]);
    }

    private function token(User $user, array $abilities = ['attendance:sync']): string
    {
        return $user->createToken('apk-test', $abilities)->plainTextToken;
    }

    private function syncPayload(array $overrides = []): array
    {
        return ['records' => [array_merge([
            'admission_number' => $this->student->admission_number,
            'date' => JalaliDate::today(),
            'status' => 'present',
        ], $overrides)]];
    }

    public function test_login_returns_token_for_attendance_marker(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->clerk->email,
            'password' => 'password',
            'device_name' => 'test-device',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'name', 'email', 'role'])
            ->assertJsonPath('email', $this->clerk->email);
    }

    public function test_login_rejects_wrong_password(): void
    {
        $this->postJson('/api/login', [
            'email' => $this->clerk->email,
            'password' => 'wrong-password',
        ])->assertStatus(401)->assertJsonMissing(['token']);
    }

    public function test_login_rejects_inactive_account(): void
    {
        $this->clerk->update(['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => $this->clerk->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_login_rejects_account_without_mark_permission(): void
    {
        $outsider = User::factory()->create(); // no roles at all

        $this->postJson('/api/login', [
            'email' => $outsider->email,
            'password' => 'password',
        ])->assertStatus(403);
    }

    public function test_sync_stores_batch_and_returns_imported_count(): void
    {
        $response = $this->withToken($this->token($this->clerk))
            ->postJson('/api/attendance/sync', $this->syncPayload());

        $response->assertOk()->assertJson(['imported' => 1, 'failed' => 0]);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id,
            'date' => JalaliDate::today(),
            'status' => 'present',
            'marked_by' => $this->clerk->id,
        ]);
    }

    public function test_sync_requires_a_token(): void
    {
        $this->postJson('/api/attendance/sync', $this->syncPayload())->assertStatus(401);
    }

    public function test_sync_rejects_token_without_sync_ability(): void
    {
        $this->withToken($this->token($this->clerk, ['other']))
            ->postJson('/api/attendance/sync', $this->syncPayload())
            ->assertStatus(403);

        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_sync_reports_unknown_student_with_code(): void
    {
        $response = $this->withToken($this->token($this->clerk))
            ->postJson('/api/attendance/sync', $this->syncPayload(['admission_number' => 'STU-9999-9999']));

        $response->assertOk()->assertJson(['imported' => 0, 'failed' => 1]);
        $this->assertSame('student_not_found', $response->json('errors.0.code'));
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_sync_rejects_row_outside_teachers_homeroom(): void
    {
        $account = User::factory()->create();
        $account->assignRole('Teacher');
        Teacher::factory()->create(['user_id' => $account->id]);
        // Note: this teacher's homeroom is NOT $this->section.

        $response = $this->withToken($this->token($account))
            ->postJson('/api/attendance/sync', $this->syncPayload());

        $response->assertOk()->assertJson(['imported' => 0, 'failed' => 1]);
        $this->assertSame('forbidden_section', $response->json('errors.0.code'));
    }

    public function test_sync_reports_invalid_date_and_status_codes(): void
    {
        $response = $this->withToken($this->token($this->clerk))->postJson('/api/attendance/sync', [
            'records' => [
                [
                    'admission_number' => $this->student->admission_number,
                    'date' => 'not-a-date',
                    'status' => 'present',
                ],
                [
                    'admission_number' => $this->student->admission_number,
                    'date' => JalaliDate::today(),
                    'status' => 'sleeping',
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['imported' => 0, 'failed' => 2]);
        $this->assertSame('invalid_date', $response->json('errors.0.code'));
        $this->assertSame('invalid_status', $response->json('errors.1.code'));
    }

    public function test_sync_rejects_future_date(): void
    {
        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());
        $future = sprintf('%04d-%02d-%02d', $year + 1, $month, $day);

        $response = $this->withToken($this->token($this->clerk))
            ->postJson('/api/attendance/sync', $this->syncPayload(['date' => $future]));

        $response->assertOk()->assertJson(['imported' => 0, 'failed' => 1]);
        $this->assertSame('future_date', $response->json('errors.0.code'));
    }

    public function test_sync_rejects_token_of_deactivated_user(): void
    {
        $token = $this->token($this->clerk);
        $this->clerk->update(['is_active' => false]);

        $this->withToken($token)
            ->postJson('/api/attendance/sync', $this->syncPayload())
            ->assertStatus(403);
    }

    public function test_session_returns_single_use_login_url(): void
    {
        $response = $this->withToken($this->token($this->clerk))
            ->postJson('/api/session', []);

        $response->assertOk()->assertJsonStructure(['url']);
        $url = $response->json('url');
        $this->assertStringContainsString('/auth/token/', $url);

        // First visit logs in and lands on the dashboard...
        $this->get($url)->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($this->clerk);

        // ...second visit finds the nonce already burned.
        $this->get($url)->assertStatus(403);
    }

    public function test_session_requires_a_token(): void
    {
        $this->postJson('/api/session', [])->assertStatus(401);
    }

    public function test_nonce_rejects_unknown_value(): void
    {
        $this->get(url('/auth/token/'.str_repeat('a', 40)))->assertStatus(403);
    }

    public function test_nonce_rejects_deactivated_user(): void
    {
        $url = $this->withToken($this->token($this->clerk))
            ->postJson('/api/session', [])->json('url');

        $this->clerk->update(['is_active' => false]);

        // 403 proves no session was created; (assertGuest is skipped - the
        // test's Bearer header persists across requests in-process, which
        // never happens over real HTTP.)
        $this->get($url)->assertStatus(403);
    }

    public function test_sync_validates_payload_shape(): void
    {
        $this->withToken($this->token($this->clerk))
            ->postJson('/api/attendance/sync', ['records' => []])
            ->assertStatus(422);

        $this->withToken($this->token($this->clerk))
            ->postJson('/api/attendance/sync', ['records' => [['admission_number' => 'x']]])
            ->assertStatus(422);
    }
}
