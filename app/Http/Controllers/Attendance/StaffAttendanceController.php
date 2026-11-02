<?php

namespace App\Http\Controllers\Attendance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\ImportAttendanceRequest;
use App\Models\Employee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\StaffAttendanceRecord;
use App\Models\Teacher;
use App\Services\AttendanceRowImporter;
use App\Services\AuditLogger;
use App\Support\JalaliDate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Staff attendance completion: the scan page marks daily presence, and
 * this is where it is reviewed - history with type/person/date/status
 * filters, Excel export, and Super Administrator deletes. Mirrors the
 * student attendance history screen on purpose (same filters, badges,
 * export columns, and delete rules) so both modules behave identically.
 */
class StaffAttendanceController extends Controller
{
    /**
     * @return array{query: \Illuminate\Database\Eloquent\Builder, type: ?string, search: ?string, from: string, to: string, statusFilter: ?string}
     */
    private function filteredQuery(Request $request): array
    {
        $type = $request->string('type')->toString() ?: null;
        if (! in_array($type, ['teacher', 'employee'], true)) {
            $type = null;
        }

        $search = trim((string) $request->input('search', ''));
        $search = $search === '' ? null : $search;

        $today = JalaliDate::today();
        [$todayYear, $todayMonth] = JalaliDate::parts($today);

        $from = JalaliDate::fromRequestArray($request->input('from')) ?? JalaliDate::startOfMonth($todayYear, $todayMonth);
        $to = JalaliDate::fromRequestArray($request->input('to')) ?? $today;

        $statusFilter = $request->string('status')->toString() ?: null;
        if (! in_array($statusFilter, ['present', 'absent', 'late', 'excused'], true)) {
            $statusFilter = null;
        }

        $query = StaffAttendanceRecord::query()
            ->when($type, fn ($q) => $q->where('attendee_type', $type))
            ->when($statusFilter, fn ($q) => $q->where('status', $statusFilter))
            ->whereBetween('date', [$from, $to]);

        if ($search) {
            $teacherIds = Teacher::query()
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
                ->pluck('id');
            $employeeIds = Employee::query()
                ->where('first_name', 'like', "%{$search}%")
                ->orWhere('last_name', 'like', "%{$search}%")
                ->orWhere('employee_code', 'like', "%{$search}%")
                ->pluck('id');

            $query->where(function ($q) use ($teacherIds, $employeeIds) {
                $q->where(function ($q2) use ($teacherIds) {
                    $q2->where('attendee_type', 'teacher')->whereIn('attendee_id', $teacherIds);
                })->orWhere(function ($q2) use ($employeeIds) {
                    $q2->where('attendee_type', 'employee')->whereIn('attendee_id', $employeeIds);
                });
            });
        }

        return compact('query', 'type', 'search', 'from', 'to', 'statusFilter');
    }

    public function index(Request $request): View
    {
        $filters = $this->filteredQuery($request);

        $statusCounts = array_fill_keys(['present', 'absent', 'late', 'excused'], 0);
        foreach ((clone $filters['query'])->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status') as $status => $count) {
            $statusCounts[$status] = $count;
        }

        $records = $filters['query']->with(['attendee', 'markedBy'])->orderByDesc('date')->paginate(30)->withQueryString();

        return view('attendance.staff-history', [
            'records' => $records,
            'statusCounts' => $statusCounts,
            'statuses' => ['present', 'absent', 'late', 'excused'],
            'type' => $filters['type'],
            'search' => $filters['search'],
            'from' => $filters['from'],
            'to' => $filters['to'],
            'statusFilter' => $filters['statusFilter'],
        ]);
    }

    /**
     * The stored status for a mark - delegated to AttendanceRowImporter so
     * rosters, scans and both imports all judge late arrivals by the very
     * same clock rule.
     */
    private function resolveStatus(string $status, ?string $timeIn, ?string $startTime): string
    {
        return AttendanceRowImporter::applyLateRule($status, $timeIn, $startTime);
    }

    /**
     * The clock a person's "late" status is judged against: their own
     * shift's start time if one is assigned, otherwise the single
     * school-wide default (Setting::school_start_time). Normalised to 5
     * characters so the comparison in resolveStatus() lines up whichever
     * source it came from - see AttendanceController::expectedStartTime()
     * for the identical rule applied to student attendance.
     */
    private function expectedStartTime(?Shift $shift): ?string
    {
        $time = $shift?->start_time ?? Setting::current()->school_start_time;

        return $time ? substr($time, 0, 5) : null;
    }

    /**
     * Daily marking roster: every active teacher and employee with a
     * status radio each, defaulting to ABSENT (not present) - the clerk
     * flips the ones who showed up, same as the student roster. Scanned
     * presence from the scan page appears here as already-marked rows.
     */
    public function mark(Request $request): View
    {
        $date = JalaliDate::fromRequestArray($request->input('date')) ?? JalaliDate::today();
        if ($date > JalaliDate::today()) {
            $date = JalaliDate::today();
        }

        $teachers = Teacher::query()->where('status', 'active')->with('shift')->orderBy('first_name')->get();
        $employees = Employee::query()->where('status', 'active')->with('shift')->orderBy('first_name')->get();

        $existing = StaffAttendanceRecord::query()
            ->onDate($date)
            ->get()
            ->keyBy(fn ($record) => $record->attendee_type.':'.$record->attendee_id);

        // Each person may sit in a different shift (unlike the student
        // roster, which is one section = one shift), so the page's JS gets
        // a per-row start time keyed "type:id" - same fallback rule as
        // resolveStatus()/expectedStartTime() above, just precomputed here
        // since a Blade view has no business calling a controller's
        // private methods.
        $startTimes = collect(['teacher' => $teachers, 'employee' => $employees])
            ->flatMap(fn ($people, $type) => $people->mapWithKeys(
                fn ($person) => ["{$type}:{$person->id}" => $this->expectedStartTime($person->shift)]
            ));

        return view('attendance.staff-mark', [
            'date' => $date,
            'teachers' => $teachers,
            'employees' => $employees,
            'existing' => $existing,
            'statuses' => ['present', 'absent', 'late', 'excused'],
            'startTimes' => $startTimes,
        ]);
    }

    public function storeMark(Request $request): RedirectResponse
    {
        $date = JalaliDate::fromRequestArray($request->input('date')) ?? JalaliDate::today();
        if ($date > JalaliDate::today()) {
            $date = JalaliDate::today();
        }

        $data = $request->validate([
            'statuses' => ['required', 'array'],
            'statuses.*' => ['required', 'in:present,absent,late,excused'],
            'time_in' => ['array'],
            'time_in.*' => ['nullable', 'date_format:H:i'],
        ]);

        // Only real, active people can be marked - anything else in the
        // payload (stale rows, tampered keys) is ignored, never created,
        // since attendee_id has no FK to guard it (polymorphic). Shifts
        // are eager-loaded here (not just the id list) so a given time_in
        // can be judged against each person's own start time below.
        $people = [
            'teacher' => Teacher::query()->where('status', 'active')->with('shift')->get()->keyBy('id'),
            'employee' => Employee::query()->where('status', 'active')->with('shift')->get()->keyBy('id'),
        ];

        $saved = 0;

        foreach ($data['statuses'] as $key => $status) {
            [$type, $id] = array_pad(explode(':', (string) $key, 2), 2, null);

            if (! in_array($type, ['teacher', 'employee'], true)
                || ! ctype_digit((string) $id)
                || ! $people[$type]->has((int) $id)) {
                continue;
            }

            $person = $people[$type]->get((int) $id);
            $timeIn = $data['time_in'][$key] ?? null;

            StaffAttendanceRecord::updateOrCreate(
                ['attendee_type' => $type, 'attendee_id' => (int) $id, 'date' => $date],
                [
                    'status' => $this->resolveStatus($status, $timeIn, $this->expectedStartTime($person->shift)),
                    'time_in' => $timeIn,
                    'marked_by' => $request->user()->id,
                ]
            );
            $saved++;
        }

        AuditLogger::log('mark', 'staff_attendance_records', null, null, [
            'date' => $date,
            'staff_count' => $saved,
        ]);

        return redirect()->route('staff-attendance.mark', ['date' => $this->dateParams($date)])
            ->with('status', __('app.attendance_saved'));
    }

    private function dateParams(string $date): array
    {
        [$year, $month, $day] = JalaliDate::parts($date);

        return ['year' => $year, 'month' => $month, 'day' => $day];
    }

    /**
     * Bulk presence from an uploaded spreadsheet: Code, Name (reference
     * only), Date, Status - one row per person per day. This is the other
     * half of the offline APK flow: the app's "Staff" sheet is written in
     * exactly this shape, so its file imports here directly. Unknown codes,
     * bad dates and bad statuses are collected per line, never fatal.
     */
    public function import(ImportAttendanceRequest $request): RedirectResponse
    {
        $spreadsheet = IOFactory::load($request->file('file')->getRealPath());
        // The offline APK writes staff rows to the "Staff" sheet (students
        // live on "Attendance") - read it by name so the two never mix.
        // Anything else falls back to the active sheet (hand-made files,
        // edited single-sheet exports).
        $sheet = $spreadsheet->getSheetByName('Staff') ?? $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);
        array_shift($rows); // header row

        // Two shapes are accepted, detected from the first data row: the
        // import shape (Code first) and our own export shape (Date first,
        // Code third: Date, Type, Code, Name, Detail, Status, Marked By).
        // This lets clerks download the export, edit statuses in Excel,
        // and upload the same file back without rearranging columns.
        $exportLayout = false;

        foreach ($rows as $row) {
            if (trim((string) ($row[0] ?? '')) === '') {
                continue;
            }

            $exportLayout = (bool) preg_match('/^\d{3,4}-\d{1,2}-\d{1,2}$/', str_replace('/', '-', trim((string) $row[0])))
                && preg_match('/^(TCH|STF)-/i', trim((string) ($row[2] ?? '')));

            break;
        }

        $imported = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2;

            // Import shape carries optional times after the reference-only
            // Detail column (Code, Name, Date, Status, Detail, Time In,
            // Time Out); the export shape carries none.
            if ($exportLayout) {
                [$date, , $code, , , $status] = array_pad($row, 6, null);
                $timeIn = $timeOut = null;
            } else {
                [$code, , $date, $status, , $timeIn, $timeOut] = array_pad($row, 7, null);
            }

            $code = trim((string) $code);
            if ($code === '') {
                continue;
            }

            $person = Teacher::with('shift')->where('employee_code', $code)->first();
            $kind = 'teacher';

            if (! $person) {
                $person = Employee::with('shift')->where('employee_code', $code)->first();
                $kind = 'employee';
            }

            if (! $person) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_staff_not_found')]);

                continue;
            }

            $parsedDate = $this->parseJalaliDate($date);
            if ($parsedDate === null || $parsedDate === false || $parsedDate > JalaliDate::today()) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_invalid_jalali_date')]);

                continue;
            }

            $statusValue = $this->normalizeStatus($status);
            if (! $statusValue) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_invalid_attendance_status')]);

                continue;
            }

            $timeInValue = $this->parseTime($timeIn);
            $timeOutValue = $this->parseTime($timeOut);

            if ($timeInValue === false || $timeOutValue === false) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_invalid_time')]);

                continue;
            }

            if ($timeInValue !== null && $timeOutValue !== null && $timeOutValue < $timeInValue) {
                $errors[] = __('app.import_row_error', ['line' => $lineNumber, 'message' => __('app.error_time_out_before_in')]);

                continue;
            }

            // Times already on the record survive a timeless row (see the
            // student importer for why).
            $update = [
                'status' => $this->resolveStatus(
                    $statusValue,
                    $timeInValue,
                    $this->expectedStartTime($person->shift)
                ),
                'marked_by' => $request->user()->id,
            ];

            if ($timeInValue !== null) {
                $update['time_in'] = $timeInValue;
            }

            if ($timeOutValue !== null) {
                $update['time_out'] = $timeOutValue;
            }

            StaffAttendanceRecord::updateOrCreate(
                ['attendee_type' => $kind, 'attendee_id' => $person->id, 'date' => $parsedDate],
                $update
            );

            $imported++;
        }

        AuditLogger::log('import', 'staff_attendance_records', null, null, [
            'imported' => $imported,
            'errors' => count($errors),
        ]);

        if ($errors) {
            return redirect()->route('staff-attendance.mark')
                ->with('status', __('app.attendance_imported_with_errors', ['count' => $imported]))
                ->with('import_errors', $errors);
        }

        return redirect()->route('staff-attendance.mark')->with('status', __('app.attendance_imported', ['count' => $imported]));
    }

    /**
     * Raw cell to a Jalali "YYYY-MM-DD" string (null = empty, false =
     * malformed) - same convention as the student import.
     */
    private function parseJalaliDate(mixed $value): string|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{3,4})-(\d{1,2})-(\d{1,2})$/', str_replace('/', '-', $value), $m)) {
            return false;
        }

        [, $year, $month, $day] = $m;

        if (! JalaliDate::isValidDate((int) $year, (int) $month, (int) $day)) {
            return false;
        }

        return JalaliDate::fromParts((int) $year, (int) $month, (int) $day);
    }

    /**
     * A time cell ("HH:MM") to itself: null for an empty cell, false for
     * anything else that isn't a real 24-hour time.
     */
    private function parseTime(mixed $value): string|false|null
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value)) {
            return false;
        }

        return $value;
    }

    /**
     * Status cell to present|absent|late|excused, accepting the English key
     * or any locale's label - same convention as the student import.
     */
    private function normalizeStatus(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        foreach (['present', 'absent', 'late', 'excused'] as $status) {
            if ($value === $status) {
                return $status;
            }

            foreach (['en', 'fa', 'ps'] as $locale) {
                if (mb_strtolower(trim(__('app.attendance_status_'.$status, [], $locale))) === $value) {
                    return $status;
                }
            }
        }

        return null;
    }

    public function export(Request $request): StreamedResponse
    {
        $filters = $this->filteredQuery($request);

        $records = $filters['query']->with(['attendee', 'markedBy'])->orderByDesc('date')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Staff Attendance');
        $sheet->setRightToLeft(app()->getLocale() !== 'en');
        $sheet->fromArray([
            __('app.field_date'), __('app.column_type'),
            __('app.field_employee_code'), __('app.column_name'),
            __('app.column_detail'), __('app.column_status'),
            __('app.field_time_in'), __('app.field_time_out'),
            __('app.field_marked_by'),
        ], null, 'A1');
        $sheet->getStyle('A1:I1')->getFont()->setBold(true);

        $row = 2;
        foreach ($records as $record) {
            $isTeacher = $record->attendee_type === 'teacher';
            $sheet->fromArray([
                $record->date,
                $isTeacher ? __('app.nav_teachers') : __('app.nav_employees'),
                $record->attendee?->employee_code,
                $record->attendee?->fullName(),
                $isTeacher
                    ? ($record->attendee?->specialization ?? '')
                    : ($record->attendee?->position ?? ''),
                __('app.attendance_status_'.$record->status),
                $record->time_in ? substr($record->time_in, 0, 5) : null,
                $record->time_out ? substr($record->time_out, 0, 5) : null,
                $record->markedBy?->name,
            ], null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $filename = 'staff-attendance-'.now()->format('Y-m-d').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Super Administrator only (route middleware + this check, exactly
     * like the student attendance destroy) - the daily scan is the
     * official record, so corrections go through delete + re-scan.
     */
    public function destroy(Request $request, StaffAttendanceRecord $record): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Super Administrator'), 403);

        $record->delete();

        AuditLogger::log('delete', 'staff_attendance_records', $record->id);

        return redirect()->route('staff-attendance.index')->with('status', __('app.attendance_deleted'));
    }
}
