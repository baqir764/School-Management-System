<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ImportAttendanceRequest;
use App\Http\Requests\Attendance\MarkAttendanceRequest;
use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\StaffAttendanceRecord;
use App\Models\Section;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Teacher;
use App\Models\Student;
use App\Services\AttendanceRowImporter;
use App\Services\AccessibleSections;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    /**
     * Daily roster: pick a section + date, see every actively-enrolled
     * student with their status for that day (defaulting to "present" for
     * anyone not yet marked, so a teacher only has to flip the exceptions).
     */
    public function index(Request $request): View
    {
        $sections = $this->accessibleSections();
        $academicYear = AcademicYear::current();

        $sectionId = $request->integer('section_id') ?: $sections->first()?->id;
        $section = $sectionId ? $sections->firstWhere('id', $sectionId) : null;

        $date = JalaliDate::fromRequestArray($request->input('date')) ?? JalaliDate::today();

        // Attendance can't be marked for a future date - clamp back to today.
        if ($date > JalaliDate::today()) {
            $date = JalaliDate::today();
        }

        $students = collect();
        $existing = collect();

        if ($section && $academicYear) {
            $students = $section->activeStudents($academicYear->id)->get();

            $existing = AttendanceRecord::query()
                ->where('section_id', $section->id)
                ->onDate($date)
                ->get()
                ->keyBy('student_id');
        }

        return view('attendance.index', [
            'sections' => $sections,
            'section' => $section,
            'academicYear' => $academicYear,
            'date' => $date,
            'students' => $students,
            'existing' => $existing,
            'statuses' => AttendanceRecord::STATUSES,
            // The whole roster shares one shift (the section's class), so a
            // single value here is enough for the page's JS to auto-flip a
            // row between present/late the moment a time is typed, instead
            // of waiting for the save round-trip to find out.
            'expectedStartTime' => $section ? $this->expectedStartTime($section->classRoom?->shift) : null,
        ]);
    }

    /**
     * The stored status for a mark: a "present" with a check-in past the
     * expected start time is analysed as late - every other choice
     * (absent, late, excused, or no times at all) stays exactly as marked.
     * This is the single rule behind roster saves, scans and the daily
     * list, so late arrivals are judged by the clock, not by memory.
     */
    private function resolveStatus(string $status, ?string $timeIn, ?string $startTime): string
    {
        // Single source of truth lives on AttendanceRowImporter (also used
        // by the web import and the APK sync) - this stays as a thin alias.
        return AttendanceRowImporter::applyLateRule($status, $timeIn, $startTime);
    }

    /**
     * The clock a person's "late" status is judged against: their own
     * shift's start time if one applies - a student's class's shift, or a
     * teacher's/employee's own shift for staff - otherwise the single
     * school-wide default (Setting::school_start_time). A shift's
     * start_time comes back "HH:MM:SS" from MySQL while
     * school_start_time is stored "HH:MM"; both are normalised to 5
     * characters so the string comparison in resolveStatus() always lines
     * up the same way regardless of which one applied.
     */
    private function expectedStartTime(?Shift $shift): ?string
    {
        $time = $shift?->start_time ?? Setting::current()->school_start_time;

        return $time ? substr($time, 0, 5) : null;
    }

    public function store(MarkAttendanceRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $date = $request->jalaliDateFromInput('date');
        $section = Section::with('classRoom.shift')->findOrFail($data['section_id']);
        $startTime = $this->expectedStartTime($section->classRoom?->shift);

        foreach ($data['statuses'] as $studentId => $status) {
            AttendanceRecord::updateOrCreate(
                [
                    'student_id' => $studentId,
                    'date' => $date,
                ],
                [
                    'section_id' => $section->id,
                    'academic_year_id' => $data['academic_year_id'],
                    'status' => $this->resolveStatus($status, $data['time_in'][$studentId] ?? null, $startTime),
                    'time_in' => $data['time_in'][$studentId] ?? null,
                    'time_out' => $data['time_out'][$studentId] ?? null,
                    'remarks' => $data['remarks'][$studentId] ?? null,
                    'marked_by' => $request->user()->id,
                ]
            );
        }

        AuditLogger::log('mark', 'attendance_records', $section->id, null, [
            'section_id' => $section->id,
            'date' => $date,
            'student_count' => count($data['statuses']),
        ]);

        [$year, $month, $day] = JalaliDate::parts($date);

        return redirect()
            ->route('attendance.index', ['section_id' => $section->id, 'date' => ['year' => $year, 'month' => $month, 'day' => $day]])
            ->with('status', __('app.attendance_saved'));
    }

    /**
     * Bulk attendance from an uploaded spreadsheet: Admission Number,
     * Student Name (reference only), Date, Status, Remarks - one row per
     * student per day, so a whole month for a whole section can be
     * imported in one file instead of the daily roster above. Unlike
     * store(), the section isn't chosen up front - each row's section is
     * derived from that student's own current enrollment, and a Teacher's
     * rows are still restricted to their own homeroom section(s) exactly
     * like MarkAttendanceRequest does for the daily roster.
     */
    public function import(ImportAttendanceRequest $request): RedirectResponse
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        // The offline APK writes students to the "Attendance" sheet (staff
        // go to a second "Staff" sheet) - read it by name so staff rows can
        // never leak into the student import. Anything else falls back to
        // the active sheet (hand-made files, our own single-sheet export).
        $sheet = $spreadsheet->getSheetByName('Attendance') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        array_shift($rows); // header row

        // Row rules live in AttendanceRowImporter (shared with the APK sync
        // API); here each failure code is just mapped to the same flash
        // message this screen has always shown, so behaviour is unchanged.
        $importer = new AttendanceRowImporter(
            $request->user(),
            $this->accessibleSections()->pluck('id'),
            AcademicYear::current()
        );

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;
            // Columns 6-7 carry optional Time In / Time Out (the APK writes
            // them there); older 5-column files simply yield nulls, which
            // the importer treats as "leave existing times alone".
            [$admissionNumber, , $date, $status, $remarks, , $timeIn, $timeOut] = array_pad($row, 8, null);

            $result = $importer->process($admissionNumber, $date, $status, $remarks, $timeIn, $timeOut);

            if ($result['ok']) {
                $imported++;

                continue;
            }

            if ($result['code'] === AttendanceRowImporter::EMPTY) {
                continue;
            }

            $errors[] = __('app.import_row_error', [
                'line' => $lineNumber,
                'message' => $this->importErrorMessage($result['code']),
            ]);
        }

        AuditLogger::log('import', 'attendance_records', null, null, [
            'imported' => $imported,
            'errors' => count($errors),
        ]);

        if ($errors) {
            return redirect()->route('attendance.index')
                ->with('status', __('app.attendance_imported_with_errors', ['count' => $imported]))
                ->with('import_errors', $errors);
        }

        return redirect()->route('attendance.index')->with('status', __('app.attendance_imported', ['count' => $imported]));
    }

    /**
     * An AttendanceRowImporter failure code to the flash message this import
     * screen has always shown for it.
     */
    private function importErrorMessage(string $code): string
    {
        return match ($code) {
            AttendanceRowImporter::STUDENT_NOT_FOUND => __('app.error_student_not_found'),
            AttendanceRowImporter::NOT_ENROLLED => __('app.error_student_not_enrolled'),
            AttendanceRowImporter::FORBIDDEN_SECTION => __('app.error_not_your_section'),
            AttendanceRowImporter::INVALID_DATE,
            AttendanceRowImporter::FUTURE_DATE => __('app.error_invalid_jalali_date'),
            AttendanceRowImporter::INVALID_TIME => __('app.error_invalid_time'),
            AttendanceRowImporter::TIME_OUT_BEFORE_IN => __('app.error_time_out_before_in'),
            default => __('app.error_invalid_attendance_status'),
        };
    }

    /**
     * QR-based daily attendance: an Attendance Clerk (or any
     * attendance.mark holder) opens this page, picks the date (defaulting
     * to today), and scans each student's ID-card QR code - either with a
     * webcam (jsQR) or by pasting/typing the admission number into the
     * box. Every successful scan records that student as PRESENT for the
     * date, keyed by their own current active enrollment's section (same
     * logic as import()), so no section picker is needed.
     */
    public function scan(Request $request): View
    {
        $date = JalaliDate::fromRequestArray($request->input('date')) ?? JalaliDate::today();
        if ($date > JalaliDate::today()) {
            $date = JalaliDate::today();
        }

        $sectionIds = $this->accessibleSections()->pluck('id');

        // Today's already-scanned students, most recent first, as a quick
        // confirmation list so the clerk can see the day filling in.
        $todayRecords = AttendanceRecord::query()
            ->whereIn('section_id', $sectionIds)
            ->onDate($date)
            ->orderByDesc('updated_at')
            ->limit(40)
            ->with(['student', 'section.classRoom'])
            ->get();

        // Same confirmation for staff scanned today (teachers + employees).
        $todayStaff = StaffAttendanceRecord::query()
            ->onDate($date)
            ->orderByDesc('updated_at')
            ->limit(40)
            ->with(['attendee'])
            ->get();

        return view('attendance.scan', [
            'academicYear' => AcademicYear::current(),
            'date' => $date,
            'todayRecords' => $todayRecords,
            'todayStaff' => $todayStaff,
        ]);
    }

    /**
     * Handle one scanned card. The QR code encodes the student's admission
     * number (see students/_id-card-back.blade.php), but the text box also
     * tolerates the full card payload or a bare typed number. Accepts
     * application/json (the webcam/manual box posts via fetch) and returns
     * a JSON result; a plain form POST falls back to a redirect+flash.
     */
    public function scanStore(Request $request): JsonResponse|RedirectResponse
    {
        $card = $this->parseCardPayload((string) $request->input('code'));

        // One scanner for everybody: staff cards mark daily presence,
        // student cards mark attendance - see scanStaff() below.
        if ($card['kind'] === 'teacher' || $card['kind'] === 'employee') {
            return $this->scanStaff($request, $card['kind'], $card['code']);
        }

        $admissionNumber = $card['code'];
        $student = $admissionNumber ? Student::where('admission_number', $admissionNumber)->first() : null;

        if (! $student) {
            return $this->scanResponse($request, false, __('app.error_student_not_found'));
        }

        $currentYear = AcademicYear::current();
        $enrollment = $currentYear
            ? $student->enrollments()->active()->with('classRoom.shift')->where('academic_year_id', $currentYear->id)->first()
            : null;

        if (! $enrollment) {
            return $this->scanResponse($request, false, __('app.error_student_not_enrolled'));
        }

        if (! $this->accessibleSections()->pluck('id')->contains($enrollment->section_id)) {
            return $this->scanResponse($request, false, __('app.error_not_your_section'));
        }

        $date = JalaliDate::fromRequestArray($request->input('date')) ?? JalaliDate::today();
        if ($date > JalaliDate::today()) {
            $date = JalaliDate::today();
        }

        $record = AttendanceRecord::updateOrCreate(
            ['student_id' => $student->id, 'date' => $date],
            [
                'section_id' => $enrollment->section_id,
                'academic_year_id' => $enrollment->academic_year_id,
                'status' => 'present',
                'remarks' => null,
                'marked_by' => $request->user()->id,
            ]
        );

        // First scan of the day stamps the check-in clock; later scans and
        // the roster keep whatever time is already there. The status is
        // then judged by that clock (late arrival → late).
        if (empty($record->time_in)) {
            $record->time_in = now()->format('H:i');
        }

        $record->status = $this->resolveStatus('present', $record->time_in, $this->expectedStartTime($enrollment->classRoom?->shift));
        $record->save();

        $alreadyPresent = ! $record->wasRecentlyCreated;

        AuditLogger::log('scan', 'attendance_records', $record->id, null, [
            'student_id' => $student->id,
            'section_id' => $enrollment->section_id,
            'date' => $date,
            'status' => $record->status,
            'time_in' => $record->time_in,
        ]);

        $message = $record->status === 'late'
            ? __('app.attendance_scan_late', ['name' => $student->fullName()])
            : __('app.attendance_scan_present', ['name' => $student->fullName()]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'already_present' => $alreadyPresent,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    /**
     * Shared JSON/redirect reply for scanStore().
     */
    private function scanResponse(Request $request, bool $ok, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $message], $ok ? 200 : 422);
        }

        return $ok
            ? back()->with('status', $message)
            : back()->withErrors(['code' => $message]);
    }

    /**
     * Pull a student's admission number out of a scanned/typed code. The
     * QR payload is just the admission number ("STU-<year>-<n>"), but the
     * box also tolerates the old multi-line card payload or a bare
     * type-in, so any of these forms still resolve:
     *
     *   STU-1404-0001                    (the QR, clean)
     *   SchoolName\nAdmission No: STU-... (legacy card payload)
     *   1404-0001                        (bare type-in)
     */
    /**
     * A scanned/typed code to ['kind' => student|teacher|employee, 'code' =>
     * ?string]. Student JSON cards carry no "t" (they predate it) and are
     * treated as students; staff JSON cards carry "t". Bare TCH-/STF-
     * type-ins resolve the same way for keyboard-wedge readers. Anything
     * unrecognised falls back to a student lookup that then 422s cleanly.
     */
    private function parseCardPayload(string $code): array
    {
        $code = trim($code);

        if (preg_match('/\{[\s\S]*\}/', $code, $m)) {
            $decoded = json_decode($m[0], true);

            if (is_array($decoded) && ! empty($decoded['a'])) {
                $kind = $decoded['t'] ?? 'student';

                if (! in_array($kind, ['student', 'teacher', 'employee'], true)) {
                    $kind = 'student';
                }

                return ['kind' => $kind, 'code' => strtoupper(trim((string) $decoded['a']))];
            }
        }

        if (preg_match('/^(TCH|STF)-\S+$/i', $code)) {
            return [
                'kind' => strtoupper($code[0]) === 'T' ? 'teacher' : 'employee',
                'code' => strtoupper($code),
            ];
        }

        return ['kind' => 'student', 'code' => $this->extractAdmissionNumber($code)];
    }

    /**
     * Mark one scanned staff card present for the day - the same single
     * scanner serves students and staff alike. One row per person per date;
     * re-scanning just confirms (already_present), exactly like the
     * student flow above. First scan of the day also stamps the check-in
     * clock, and status is then judged against that person's own shift (or
     * the school-wide default) - the same rule the student scan uses.
     */
    private function scanStaff(Request $request, string $kind, ?string $code): JsonResponse|RedirectResponse
    {
        $person = $kind === 'teacher'
            ? Teacher::with('shift')->where('employee_code', $code)->first()
            : Employee::with('shift')->where('employee_code', $code)->first();

        if (! $person) {
            return $this->scanResponse($request, false, __('app.error_staff_not_found'));
        }

        $date = JalaliDate::fromRequestArray($request->input('date')) ?? JalaliDate::today();
        if ($date > JalaliDate::today()) {
            $date = JalaliDate::today();
        }

        $record = StaffAttendanceRecord::updateOrCreate(
            ['attendee_type' => $kind, 'attendee_id' => $person->id, 'date' => $date],
            ['status' => 'present', 'marked_by' => $request->user()->id]
        );

        if (empty($record->time_in)) {
            $record->time_in = now()->format('H:i');
        }

        $record->status = $this->resolveStatus('present', $record->time_in, $this->expectedStartTime($person->shift));
        $record->save();

        $alreadyPresent = ! $record->wasRecentlyCreated;

        $detail = $kind === 'teacher'
            ? ($person->specialization ?? '')
            : ($person->position ?? '');

        AuditLogger::log('scan', 'staff_attendance_records', $record->id, null, [
            'attendee_type' => $kind,
            'attendee_id' => $person->id,
            'date' => $date,
            'status' => $record->status,
            'time_in' => $record->time_in,
        ]);

        $message = $record->status === 'late'
            ? __('app.staff_scan_late', ['name' => $person->fullName(), 'detail' => $detail])
            : __('app.staff_scan_identified', ['name' => $person->fullName(), 'detail' => $detail]);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'kind' => $kind,
                'already_present' => $alreadyPresent,
                'name' => $person->fullName(),
                'detail' => $detail,
                'status' => $record->status,
                'message' => $message,
            ]);
        }

        return back()->with('status', $message);
    }

    private function extractAdmissionNumber(string $code): ?string
    {
        $code = trim($code);

        if ($code === '') {
            return null;
        }

        if (preg_match('/\bSTU-\d{4}-\d+\b/i', $code, $m)) {
            return strtoupper($m[0]);
        }

        if (preg_match('/^\d{4}-\d{1,4}$/', $code)) {
            return 'STU-'.$code;
        }

        return null;
    }

    /**
     * Attendance history for a section (and optionally a single student),
     * over a date range - defaults to the current month.
     */
    public function history(Request $request): View
    {
        $sections = $this->accessibleSections();

        $sectionId = $request->integer('section_id') ?: $sections->first()?->id;
        $section = $sectionId ? $sections->firstWhere('id', $sectionId) : null;

        $today = JalaliDate::today();
        [$todayYear, $todayMonth] = JalaliDate::parts($today);

        $from = JalaliDate::fromRequestArray($request->input('from')) ?? JalaliDate::startOfMonth($todayYear, $todayMonth);
        $to = JalaliDate::fromRequestArray($request->input('to')) ?? $today;

        $studentId = $request->integer('student_id') ?: null;

        $statusFilter = $request->string('status')->toString() ?: null;
        if (! in_array($statusFilter, AttendanceRecord::STATUSES, true)) {
            $statusFilter = null;
        }

        $records = collect();
        $students = collect();
        $statusCounts = array_fill_keys(AttendanceRecord::STATUSES, 0);

        if ($section) {
            $students = $section->activeStudents()->get();

            $baseQuery = AttendanceRecord::query()
                ->where('section_id', $section->id)
                ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
                ->whereBetween('date', [$from, $to]);

            // Per-status totals for the current scope (section/student/date
            // range) - deliberately ignoring the status filter itself, so
            // the summary always shows the full distribution.
            $statusCounts = array_merge($statusCounts, (clone $baseQuery)
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
                ->all());

            $records = (clone $baseQuery)
                ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
                ->with(['student', 'markedBy'])
                ->orderByDesc('date')
                ->paginate(30)
                ->withQueryString();
        }

        return view('attendance.history', [
            'sections' => $sections,
            'section' => $section,
            'students' => $students,
            'studentId' => $studentId,
            'from' => $from,
            'to' => $to,
            'records' => $records,
            'statuses' => AttendanceRecord::STATUSES,
            'statusFilter' => $statusFilter,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Export the filtered attendance history to Excel (.xlsx) - same
     * filters as history() (section/student/date range), but the full
     * result set instead of a paginated page. Columns mirror the import
     * format (Admission Number, Student Name, Date, Status, Remarks) plus
     * Marked By, so an export can be reviewed and re-imported. Teachers
     * stay scoped to their own homeroom section(s) via
     * accessibleSections(), exactly like history().
     */
    public function export(Request $request): StreamedResponse
    {
        $sections = $this->accessibleSections();

        $sectionId = $request->integer('section_id') ?: $sections->first()?->id;
        $section = $sectionId ? $sections->firstWhere('id', $sectionId) : null;

        $today = JalaliDate::today();
        [$todayYear, $todayMonth] = JalaliDate::parts($today);

        $from = JalaliDate::fromRequestArray($request->input('from')) ?? JalaliDate::startOfMonth($todayYear, $todayMonth);
        $to = JalaliDate::fromRequestArray($request->input('to')) ?? $today;

        $studentId = $request->integer('student_id') ?: null;

        $statusFilter = $request->string('status')->toString() ?: null;
        if (! in_array($statusFilter, AttendanceRecord::STATUSES, true)) {
            $statusFilter = null;
        }

        $records = collect();

        if ($section) {
            $records = AttendanceRecord::query()
                ->where('section_id', $section->id)
                ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
                ->whereBetween('date', [$from, $to])
                ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
                ->with(['student', 'markedBy'])
                ->orderByDesc('date')
                ->get();
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Attendance');
        $sheet->setRightToLeft(app()->getLocale() !== 'en');
        $sheet->fromArray([
            __('app.field_admission_number'), __('app.field_student_name'),
            __('app.field_date'), __('app.column_status'),
            __('app.field_time_in'), __('app.field_time_out'),
            __('app.field_remarks'), __('app.field_marked_by'),
        ], null, 'A1');
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);

        $row = 2;
        foreach ($records as $record) {
            $sheet->fromArray([
                $record->student->admission_number,
                $record->student->fullName(),
                $record->date,
                __('app.attendance_status_'.$record->status),
                $record->time_in ? substr($record->time_in, 0, 5) : null,
                $record->time_out ? substr($record->time_out, 0, 5) : null,
                $record->remarks,
                $record->markedBy?->name,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'H') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'attendance-history-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Delete a single attendance history record. Super Administrator
     * only - attendance is the official daily record, so nobody else
     * (not even School Administrator) may remove it.
     */
    public function destroy(Request $request, AttendanceRecord $record): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Super Administrator'), 403);

        $record->delete();

        AuditLogger::log('delete', 'attendance_records', $record->id, [
            'student_id' => $record->student_id,
            'section_id' => $record->section_id,
            'date' => $record->date,
            'status' => $record->status,
        ]);

        return back()->with('status', __('app.attendance_deleted'));
    }

    /**
     * Sections the current user may mark/view attendance for: every
     * active section for School Administrator/Super Administrator and
     * Principal (view-only), just their own homeroom section(s) for a
     * Teacher.
     */
    private function accessibleSections(): Collection
    {
        // Single source of truth lives in AccessibleSections (also used by
        // the APK sync API) - this stays as a thin local alias.
        return AccessibleSections::for(request()->user());
    }
}
