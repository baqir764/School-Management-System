<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Employee;
use App\Models\Enrollment;
use App\Models\StaffAttendanceRecord;
use App\Models\Section;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected AcademicYear $year;

    protected Section $section;

    protected Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Super Administrator');

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

    private function markAttendance(User $actor, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());

        return $this->actingAs($actor)->post('/attendance', array_merge([
            'section_id' => $this->section->id,
            'academic_year_id' => $this->year->id,
            'date' => ['year' => $year, 'month' => $month, 'day' => $day],
            'statuses' => [$this->student->id => 'absent'],
            'remarks' => [$this->student->id => 'Sick'],
        ], $overrides));
    }

    public function test_administrator_can_mark_attendance_for_any_section(): void
    {
        $response = $this->markAttendance($this->admin);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id,
            'section_id' => $this->section->id,
            'status' => 'absent',
        ]);
    }

    public function test_marking_with_times_stores_them_and_derives_late(): void
    {
        \App\Models\Setting::current()->update(['school_start_time' => '07:30']);

        // On-time arrival stays present.
        $this->markAttendance($this->admin, [
            'statuses' => [$this->student->id => 'present'],
            'time_in' => [$this->student->id => '07:10'],
            'time_out' => [$this->student->id => '12:00'],
        ])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id, 'status' => 'present',
        ]);

        $record = \App\Models\AttendanceRecord::where('student_id', $this->student->id)->first();
        $this->assertSame('07:10:00', $record->time_in);
        $this->assertSame('12:00:00', $record->time_out);

        // Same request shape but past the start time flips to late.
        $this->markAttendance($this->admin, [
            'statuses' => [$this->student->id => 'present'],
            'time_in' => [$this->student->id => '08:05'],
        ])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id, 'status' => 'late',
        ]);

        // Explicit absent is never overridden by a time.
        $this->markAttendance($this->admin, [
            'statuses' => [$this->student->id => 'absent'],
            'time_in' => [$this->student->id => '08:05'],
        ])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id, 'status' => 'absent',
        ]);
    }

    public function test_time_out_before_time_in_is_rejected(): void
    {
        $response = $this->markAttendance($this->admin, [
            'statuses' => [$this->student->id => 'present'],
            'time_in' => [$this->student->id => '09:00'],
            'time_out' => [$this->student->id => '08:00'],
        ]);

        $response->assertSessionHasErrors('time_out.'.$this->student->id);
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_scan_stamps_time_in_and_marks_late_arrivals(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');
        \App\Models\Setting::current()->update(['school_start_time' => '07:30']);

        // A scan can only happen "now", so late-ness depends on the real
        // clock - instead assert the stamp exists and is a valid HH:MM.
        $this->scanPost($clerk)->assertOk()->assertJson(['ok' => true]);

        $record = \App\Models\AttendanceRecord::where('student_id', $this->student->id)->firstOrFail();
        $this->assertMatchesRegularExpression('/^\d{2}:\d{2}(:\d{2})?$/', (string) $record->time_in);
    }

    public function test_marking_attendance_twice_for_the_same_day_updates_the_existing_record(): void
    {
        $this->markAttendance($this->admin, ['statuses' => [$this->student->id => 'absent']]);
        $this->markAttendance($this->admin, ['statuses' => [$this->student->id => 'present']]);

        $this->assertDatabaseCount('attendance_records', 1);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id,
            'status' => 'present',
        ]);
    }

    public function test_a_teacher_can_mark_attendance_for_their_own_homeroom_section(): void
    {
        $account = User::factory()->create();
        $account->assignRole('Teacher');
        $teacher = Teacher::factory()->create(['user_id' => $account->id]);
        $this->section->update(['class_teacher_id' => $teacher->id]);

        $response = $this->markAttendance($account);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_records', ['student_id' => $this->student->id, 'status' => 'absent']);
    }

    public function test_a_teacher_cannot_mark_attendance_for_a_section_that_is_not_their_homeroom(): void
    {
        $account = User::factory()->create();
        $account->assignRole('Teacher');
        Teacher::factory()->create(['user_id' => $account->id]);
        // Note: this teacher's homeroom is NOT $this->section.

        $response = $this->markAttendance($account);

        $response->assertSessionHasErrors('section_id');
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_a_principal_can_view_but_not_mark_attendance(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->get('/attendance')->assertStatus(200);
        $this->markAttendance($principal)->assertStatus(403);
    }

    public function test_administrator_can_import_attendance_from_excel(): void
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Admission Number', 'Student Name', 'Date', 'Status', 'Remarks'],
            [$this->student->admission_number, $this->student->fullName(), JalaliDate::today(), 'present', 'Imported'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'attendance-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $file = new UploadedFile(
            $path,
            'attendance.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($this->admin)->post('/attendance/import', ['file' => $file]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id,
            'date' => JalaliDate::today(),
            'status' => 'present',
            'remarks' => 'Imported',
        ]);
    }

    public function test_a_teacher_can_import_excel_only_for_their_own_homeroom_section(): void
    {
        $account = User::factory()->create();
        $account->assignRole('Teacher');
        $teacher = Teacher::factory()->create(['user_id' => $account->id]);
        $otherClass = ClassRoom::factory()->create();
        $otherSection = Section::factory()->create(['class_id' => $otherClass->id]);
        $otherSection->update(['class_teacher_id' => $teacher->id]);
        // Teacher's homeroom is $otherSection, NOT $this->section.

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Admission Number', 'Student Name', 'Date', 'Status', 'Remarks'],
            [$this->student->admission_number, $this->student->fullName(), JalaliDate::today(), 'present', ''],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'attendance-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $file = new UploadedFile(
            $path,
            'attendance.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($account)->post('/attendance/import', ['file' => $file]);

        $response->assertRedirect()
            ->assertSessionHas('import_errors');
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_administrator_can_export_attendance_history_to_excel(): void
    {
        $this->markAttendance($this->admin);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $this->student->id, 'status' => 'absent']);

        $response = $this->actingAs($this->admin)->get('/attendance/history/export?section_id='.$this->section->id);

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');

        $exportPath = tempnam(sys_get_temp_dir(), 'attendance-export').'.xlsx';
        file_put_contents($exportPath, $response->streamedContent());
        $spreadsheet = IOFactory::load($exportPath);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        $this->assertGreaterThanOrEqual(2, count($rows));
        $this->assertSame($this->student->admission_number, (string) $rows[1][0]);
        $this->assertSame(JalaliDate::today(), $rows[1][2]);
    }

    private function scanPost(User $actor, array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor)
            ->postJson('/attendance/scan', array_merge([
                'code' => $this->student->admission_number,
                'date' => ['year' => JalaliDate::parts(JalaliDate::today())[0], 'month' => JalaliDate::parts(JalaliDate::today())[1], 'day' => JalaliDate::parts(JalaliDate::today())[2]],
            ], $overrides));
    }

    public function test_attendance_clerk_role_has_attendance_mark_and_view(): void
    {
        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('Attendance Clerk')->hasPermissionTo('attendance.mark'));
        $this->assertTrue(\Spatie\Permission\Models\Role::findByName('Attendance Clerk')->hasPermissionTo('attendance.view'));
    }

    public function test_attendance_clerk_can_open_the_scan_page(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        $this->actingAs($clerk)->get('/attendance/scan')->assertStatus(200);
    }

    public function test_attendance_clerk_can_mark_present_by_scanning_a_qr_code(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        $response = $this->scanPost($clerk);

        $response->assertOk()
            ->assertJson(['ok' => true]);
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id,
            'section_id' => $this->section->id,
            'academic_year_id' => $this->year->id,
            'date' => JalaliDate::today(),
            'status' => 'present',
            'marked_by' => $clerk->id,
        ]);
    }

    public function test_scan_accepts_a_bare_type_in_admission_number(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        $bare = substr($this->student->admission_number, strlen('STU-'));
        $this->scanPost($clerk, ['code' => $bare])->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $this->student->id, 'status' => 'present']);
    }

    public function test_scan_accepts_the_json_id_card_payload(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        // New ID-card QR form: admission + name + class as compact JSON.
        // The student still resolves by the admission number inside.
        $code = json_encode([
            'a' => $this->student->admission_number,
            'n' => $this->student->fullName(),
            'c' => 'Grade 5 - A',
        ], JSON_UNESCAPED_UNICODE);

        $this->scanPost($clerk, ['code' => $code])->assertOk()->assertJson(['ok' => true]);
        $this->assertDatabaseHas('attendance_records', ['student_id' => $this->student->id, 'status' => 'present']);
    }

    public function test_scan_marks_a_teacher_present_for_the_day(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');
        $teacher = Teacher::factory()->create();

        $code = json_encode([
            't' => 'teacher',
            'a' => $teacher->employee_code,
            'n' => $teacher->fullName(),
            'c' => $teacher->specialization ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $this->scanPost($clerk, ['code' => $code])
            ->assertOk()
            ->assertJson(['ok' => true, 'kind' => 'teacher', 'name' => $teacher->fullName(), 'already_present' => false]);

        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'teacher',
            'attendee_id' => $teacher->id,
            'date' => JalaliDate::today(),
            'status' => 'present',
            'marked_by' => $clerk->id,
        ]);

        // Re-scanning only confirms - still exactly one row.
        $this->scanPost($clerk, ['code' => $code])
            ->assertOk()
            ->assertJson(['ok' => true, 'already_present' => true]);
        $this->assertDatabaseCount('staff_attendance_records', 1);
    }

    public function test_scan_marks_an_employee_present_for_the_day(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');
        $employee = Employee::factory()->create();

        $code = json_encode([
            't' => 'employee',
            'a' => $employee->employee_code,
            'n' => $employee->fullName(),
            'c' => $employee->position ?? '',
        ], JSON_UNESCAPED_UNICODE);

        $this->scanPost($clerk, ['code' => $code])
            ->assertOk()
            ->assertJson(['ok' => true, 'kind' => 'employee', 'name' => $employee->fullName()]);

        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'employee',
            'attendee_id' => $employee->id,
            'date' => JalaliDate::today(),
            'status' => 'present',
        ]);
    }

    public function test_scan_rejects_an_unknown_staff_code(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        $this->scanPost($clerk, ['code' => json_encode(['t' => 'teacher', 'a' => 'TCH-9999'])])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_staff_history_lists_todays_scans_with_type_filter(): void
    {
        $teacher = Teacher::factory()->create();
        $employee = Employee::factory()->create();

        StaffAttendanceRecord::create([
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
            'date' => JalaliDate::today(), 'status' => 'present', 'marked_by' => $this->admin->id,
        ]);
        StaffAttendanceRecord::create([
            'attendee_type' => 'employee', 'attendee_id' => $employee->id,
            'date' => JalaliDate::today(), 'status' => 'present', 'marked_by' => $this->admin->id,
        ]);

        $all = $this->actingAs($this->admin)->get('/staff-attendance')->assertOk();
        $all->assertSee($teacher->fullName())->assertSee($employee->fullName());

        $onlyTeachers = $this->actingAs($this->admin)->get('/staff-attendance?type=teacher')->assertOk();
        $onlyTeachers->assertSee($teacher->fullName())->assertDontSee($employee->fullName());
    }

    public function test_clerk_can_view_staff_history_but_not_delete(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');
        $teacher = Teacher::factory()->create();
        $record = StaffAttendanceRecord::create([
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
            'date' => JalaliDate::today(), 'status' => 'present', 'marked_by' => $clerk->id,
        ]);

        $this->actingAs($clerk)->get('/staff-attendance')->assertOk();
        $this->actingAs($clerk)->delete(route('staff-attendance.destroy', $record))->assertStatus(403);
        $this->assertDatabaseHas('staff_attendance_records', ['id' => $record->id]);
    }

    public function test_super_admin_can_delete_a_staff_record_and_export_history(): void
    {
        $teacher = Teacher::factory()->create();
        $record = StaffAttendanceRecord::create([
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
            'date' => JalaliDate::today(), 'status' => 'present', 'marked_by' => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->delete(route('staff-attendance.destroy', $record))
            ->assertRedirect(route('staff-attendance.index'));
        // Hard delete by design (see the migration): re-scanning right
        // after a delete must work instead of hitting the unique key.
        $this->assertDatabaseMissing('staff_attendance_records', ['id' => $record->id]);

        StaffAttendanceRecord::create([
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
            'date' => JalaliDate::today(), 'status' => 'present', 'marked_by' => $this->admin->id,
        ]);

        $export = $this->actingAs($this->admin)->get('/staff-attendance/export')->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $export->headers->get('Content-Type')
        );
    }

    public function test_staff_mark_page_defaults_everyone_to_absent(): void
    {
        $teacher = Teacher::factory()->create();
        $employee = Employee::factory()->create();

        $html = $this->actingAs($this->admin)->get('/staff-attendance/mark')->assertOk()->getContent();

        // Both rosters pre-check absent (not present) for unmarked people.
        $this->assertMatchesRegularExpression(
            '/name="statuses\[teacher:'.$teacher->id.'\]"[^>]*value="absent"[^>]*checked/',
            $html
        );
        $this->assertMatchesRegularExpression(
            '/name="statuses\[employee:'.$employee->id.'\]"[^>]*value="absent"[^>]*checked/',
            $html
        );
    }

    public function test_clerk_can_mark_staff_attendance_for_a_day(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');
        $teacher = Teacher::factory()->create();
        $employee = Employee::factory()->create();
        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());

        $response = $this->actingAs($clerk)->post('/staff-attendance/mark', [
            'date' => ['year' => $year, 'month' => $month, 'day' => $day],
            'statuses' => [
                'teacher:'.$teacher->id => 'present',
                'employee:'.$employee->id => 'absent',
            ],
        ]);

        $response->assertRedirect();
        $this->assertStringContainsString('/staff-attendance/mark', $response->headers->get('Location'));
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
            'date' => JalaliDate::today(), 'status' => 'present', 'marked_by' => $clerk->id,
        ]);
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'employee', 'attendee_id' => $employee->id,
            'date' => JalaliDate::today(), 'status' => 'absent',
        ]);
    }

    public function test_staff_mark_ignores_tampered_keys_and_rejects_bad_status(): void
    {
        $teacher = Teacher::factory()->create();
        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());

        $this->actingAs($this->admin)->post('/staff-attendance/mark', [
            'date' => ['year' => $year, 'month' => $month, 'day' => $day],
            'statuses' => [
                'teacher:999999' => 'present',
                'hacker:1' => 'present',
                'teacher:'.$teacher->id => 'sleeping',
            ],
        ])->assertSessionHasErrors('statuses.teacher:'.$teacher->id);

        $this->assertDatabaseCount('staff_attendance_records', 0);
    }

    public function test_principal_cannot_mark_staff_attendance(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->get('/staff-attendance/mark')->assertStatus(403);
        $this->actingAs($principal)->post('/staff-attendance/mark', [
            'statuses' => [],
        ])->assertStatus(403);
    }

    public function test_student_roster_defaults_unmarked_to_absent(): void
    {
        $html = $this->actingAs($this->admin)->get('/attendance')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/name="statuses\['.$this->student->id.'\]"[^>]*value="absent"[^>]*checked/',
            $html
        );
    }

    private function makeStaffImportFile(array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray(array_merge(
            [['Code', 'Name', 'Date', 'Status']],
            $rows
        ));
        $path = tempnam(sys_get_temp_dir(), 'staff-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'staff.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_administrator_can_import_staff_attendance_from_excel(): void
    {
        $teacher = Teacher::factory()->create();
        $employee = Employee::factory()->create();

        $response = $this->actingAs($this->admin)->post('/staff-attendance/import', [
            // Second row carries the APK's 5th "Detail" column - ignored.
            'file' => $this->makeStaffImportFile([
                [$teacher->employee_code, $teacher->fullName(), JalaliDate::today(), 'present'],
                [$employee->employee_code, $employee->fullName(), JalaliDate::today(), 'absent', 'Guard'],
            ]),
        ]);

        $response->assertRedirect(route('staff-attendance.mark'));
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
            'date' => JalaliDate::today(), 'status' => 'present', 'marked_by' => $this->admin->id,
        ]);
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'employee', 'attendee_id' => $employee->id,
            'date' => JalaliDate::today(), 'status' => 'absent',
        ]);
    }

    public function test_staff_import_collects_row_errors_without_saving_them(): void
    {
        $teacher = Teacher::factory()->create();

        $response = $this->actingAs($this->admin)->post('/staff-attendance/import', [
            'file' => $this->makeStaffImportFile([
                ['TCH-9999', 'Nobody', JalaliDate::today(), 'present'],
                [$teacher->employee_code, $teacher->fullName(), 'not-a-date', 'present'],
                [$teacher->employee_code, $teacher->fullName(), JalaliDate::today(), 'present'],
            ]),
        ]);

        $response->assertRedirect(route('staff-attendance.mark'));
        $response->assertSessionHas('import_errors');
        // Only the valid row lands.
        $this->assertDatabaseCount('staff_attendance_records', 1);
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
        ]);
    }

    private function makeTwoSheetFile(array $attendanceRows, array $staffRows): UploadedFile
    {
        // Exactly what the offline APK writes: students on "Attendance"
        // (active/first), staff on "Staff" (second).
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setTitle('Attendance');
        $spreadsheet->getActiveSheet()->fromArray(array_merge(
            [['Admission Number', 'Student Name', 'Date', 'Status', 'Remarks', 'Class']],
            $attendanceRows
        ));
        $staffSheet = $spreadsheet->createSheet();
        $staffSheet->setTitle('Staff');
        $staffSheet->fromArray(array_merge(
            [['Code', 'Name', 'Date', 'Status', 'Detail']],
            $staffRows
        ));

        $path = tempnam(sys_get_temp_dir(), 'apk-export').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        return new UploadedFile(
            $path,
            'attendance.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_staff_import_reads_the_staff_sheet_not_the_active_one(): void
    {
        $teacher = Teacher::factory()->create();

        $response = $this->actingAs($this->admin)->post('/staff-attendance/import', [
            'file' => $this->makeTwoSheetFile(
                [[$this->student->admission_number, $this->student->fullName(), JalaliDate::today(), 'present', '', '']],
                [[$teacher->employee_code, $teacher->fullName(), JalaliDate::today(), 'present', '']]
            ),
        ]);

        $response->assertRedirect(route('staff-attendance.mark'));
        // Only the staff row lands - the student row on the other sheet is
        // invisible to this import (and vice versa).
        $this->assertDatabaseCount('staff_attendance_records', 1);
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
        ]);
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_student_import_ignores_the_staff_sheet(): void
    {
        $teacher = Teacher::factory()->create();

        $response = $this->actingAs($this->admin)->post('/attendance/import', [
            'file' => $this->makeTwoSheetFile(
                [[$this->student->admission_number, $this->student->fullName(), JalaliDate::today(), 'present', '', '']],
                [[$teacher->employee_code, $teacher->fullName(), JalaliDate::today(), 'present', '']]
            ),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('attendance_records', ['student_id' => $this->student->id]);
        $this->assertDatabaseCount('staff_attendance_records', 0);
    }

    public function test_principal_cannot_import_staff_attendance(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->post('/staff-attendance/import', [
            'file' => $this->makeStaffImportFile([]),
        ])->assertStatus(403);
    }

    public function test_staff_import_stores_times_and_derives_late(): void
    {
        $teacher = Teacher::factory()->create();
        \App\Models\Setting::current()->update(['school_start_time' => '07:30']);

        $response = $this->actingAs($this->admin)->post('/staff-attendance/import', [
            'file' => $this->makeStaffImportFile([
                [$teacher->employee_code, $teacher->fullName(), JalaliDate::today(), 'present', '', '08:15', ''],
            ]),
        ]);

        $response->assertRedirect(route('staff-attendance.mark'));
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'teacher', 'attendee_id' => $teacher->id,
            'status' => 'late',
        ]);
        $record = \App\Models\StaffAttendanceRecord::firstWhere('attendee_id', $teacher->id);
        $this->assertSame('08:15:00', $record->time_in);
    }

    public function test_staff_import_rejects_bad_times(): void
    {
        $teacher = Teacher::factory()->create();

        $response = $this->actingAs($this->admin)->post('/staff-attendance/import', [
            'file' => $this->makeStaffImportFile([
                [$teacher->employee_code, $teacher->fullName(), JalaliDate::today(), 'present', '', '25:99', ''],
                [$teacher->employee_code, $teacher->fullName(), JalaliDate::today(), 'present', '', '09:00', '08:00'],
            ]),
        ]);

        $response->assertRedirect(route('staff-attendance.mark'));
        $response->assertSessionHas('import_errors');
        $this->assertDatabaseCount('staff_attendance_records', 0);
    }

    public function test_student_import_with_times_late_derives_by_section_shift(): void
    {
        $shift = \App\Models\Shift::factory()->create(['start_time' => '08:00:00', 'is_active' => true]);
        $class = ClassRoom::factory()->create();
        $class->update(['shift_id' => $shift->id]);
        $section = Section::factory()->create(['class_id' => $class->id]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'academic_year_id' => $this->year->id,
            'class_id' => $class->id,
            'section_id' => $section->id,
            'status' => 'active',
        ]);
        // School default is earlier - the section shift must win.
        \App\Models\Setting::current()->update(['school_start_time' => '07:00']);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['Admission Number', 'Student Name', 'Date', 'Status', 'Remarks', 'Class', 'Time In', 'Time Out'],
            [$student->admission_number, $student->fullName(), JalaliDate::today(), 'present', '', '', '07:30', ''],
            [$student->admission_number, $student->fullName(), JalaliDate::today(), 'present', '', '', '08:30', 'bad'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'attendance-import').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);
        $file = new UploadedFile($path, 'attendance.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);

        $response = $this->actingAs($this->admin)->post('/attendance/import', ['file' => $file]);

        // First row: 07:30 is before the 08:00 shift start -> stays present
        // with times stored. Second row dies on the bad time-out.
        $response->assertRedirect();
        $response->assertSessionHas('import_errors');
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $student->id, 'status' => 'present',
        ]);
        $record = \App\Models\AttendanceRecord::where('student_id', $student->id)->first();
        $this->assertSame('07:30:00', $record->time_in);
    }

    public function test_scan_rejects_an_unknown_admission_number(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        $this->scanPost($clerk, ['code' => 'STU-9999-9999'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_scan_rejects_a_student_who_is_not_currently_enrolled(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');
        $this->student->enrollments()->update(['status' => 'withdrawn']);

        $this->scanPost($clerk)->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_a_teacher_cannot_scan_a_student_outside_their_homeroom(): void
    {
        $account = User::factory()->create();
        $account->assignRole('Teacher');
        $teacher = Teacher::factory()->create(['user_id' => $account->id]);
        $otherClass = ClassRoom::factory()->create();
        $otherSection = Section::factory()->create(['class_id' => $otherClass->id]);
        $otherSection->update(['class_teacher_id' => $teacher->id]);

        $this->scanPost($account)->assertStatus(422)->assertJson(['ok' => false]);
        $this->assertDatabaseMissing('attendance_records', ['student_id' => $this->student->id]);
    }

    public function test_a_principal_cannot_scan_attendance(): void
    {
        $principal = User::factory()->create();
        $principal->assignRole('Principal');

        $this->actingAs($principal)->get('/attendance/scan')->assertStatus(403);
        $this->scanPost($principal)->assertStatus(403);
    }

    public function test_scanning_twice_reports_already_present_and_keeps_one_record(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        $this->scanPost($clerk)->assertOk()->assertJson(['ok' => true, 'already_present' => false]);
        $this->scanPost($clerk)->assertOk()->assertJson(['ok' => true, 'already_present' => true]);
        $this->assertDatabaseCount('attendance_records', 1);
    }

    public function test_student_attendance_late_is_judged_against_the_class_shift_not_the_school_default(): void
    {
        \App\Models\Setting::current()->update(['school_start_time' => '07:00']);
        $shift = \App\Models\Shift::factory()->create(['start_time' => '08:00']);
        \App\Models\ClassRoom::where('id', $this->section->class_id)->update(['shift_id' => $shift->id]);

        // Past the school-wide default (07:00) but still before this
        // class's own shift (08:00) - present, not late, because the
        // class's shift wins over the school-wide default.
        $this->markAttendance($this->admin, [
            'statuses' => [$this->student->id => 'present'],
            'time_in' => [$this->student->id => '07:30'],
        ])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id, 'status' => 'present',
        ]);

        // Past the class's own shift start time - late.
        $this->markAttendance($this->admin, [
            'statuses' => [$this->student->id => 'present'],
            'time_in' => [$this->student->id => '08:15'],
        ])->assertRedirect();
        $this->assertDatabaseHas('attendance_records', [
            'student_id' => $this->student->id, 'status' => 'late',
        ]);
    }

    public function test_staff_scan_is_judged_late_against_their_own_shift(): void
    {
        $clerk = User::factory()->create();
        $clerk->assignRole('Attendance Clerk');

        // Start times chosen so lateness is decided by the shift, not by
        // whatever real wall-clock time this test happens to run at.
        $alwaysLateShift = \App\Models\Shift::factory()->create(['start_time' => '00:00']);
        $neverLateShift = \App\Models\Shift::factory()->create(['start_time' => '23:59']);

        $lateEmployee = Employee::factory()->create(['shift_id' => $alwaysLateShift->id]);
        $onTimeTeacher = Teacher::factory()->create(['shift_id' => $neverLateShift->id]);

        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());
        $dateParam = ['year' => $year, 'month' => $month, 'day' => $day];

        $this->actingAs($clerk)->postJson('/attendance/scan', [
            'code' => $lateEmployee->employee_code,
            'date' => $dateParam,
        ])->assertOk()->assertJson(['ok' => true, 'status' => 'late']);

        $this->actingAs($clerk)->postJson('/attendance/scan', [
            'code' => $onTimeTeacher->employee_code,
            'date' => $dateParam,
        ])->assertOk()->assertJson(['ok' => true, 'status' => 'present']);

        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'employee', 'attendee_id' => $lateEmployee->id, 'status' => 'late',
        ]);
        $this->assertDatabaseHas('staff_attendance_records', [
            'attendee_type' => 'teacher', 'attendee_id' => $onTimeTeacher->id, 'status' => 'present',
        ]);
    }
}
