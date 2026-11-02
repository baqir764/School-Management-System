<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\Student;
use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Support\Collection;

/**
 * One attendance row - shared by the web Excel import
 * (AttendanceController::import) and the APK sync API
 * (Api\AttendanceApiController::sync), so both enforce exactly the same
 * rules: the student must exist, must hold an ACTIVE enrollment in the
 * current academic year, the marking user must be allowed to touch that
 * enrollment's section, the date must be a real non-future Jalali date,
 * and the status must be a known key (English itself or any of the app's
 * three locale labels).
 *
 * Returns ['ok' => true, ...] on success, otherwise
 * ['ok' => false, 'code' => self::<REASON>]. Callers translate codes into
 * their own messages (web flash strings vs. API JSON the APK localizes).
 */
final class AttendanceRowImporter
{
    public const EMPTY = 'empty';

    public const STUDENT_NOT_FOUND = 'student_not_found';

    public const NOT_ENROLLED = 'not_enrolled';

    public const FORBIDDEN_SECTION = 'forbidden_section';

    public const INVALID_DATE = 'invalid_date';

    public const FUTURE_DATE = 'future_date';

    public const INVALID_STATUS = 'invalid_status';

    public const INVALID_TIME = 'invalid_time';

    public const TIME_OUT_BEFORE_IN = 'time_out_before_in';

    public function __construct(
        private User $marker,
        private Collection $accessibleSectionIds,
        private ?AcademicYear $currentYear,
    ) {}

    /**
     * A "present" with a check-in past the applicable start time is
     * analysed as late - every other choice stays as marked. The caller
     * passes the already-resolved start (shift-aware); the school-wide
     * default lives in Setting::school_start_time when nothing else
     * applies. Single rule used by the roster, the scan page, imports and
     * the APK sync alike, so late arrivals are judged by the clock, never
     * by memory. Empty start means no automatic analysis at all.
     */
    public static function applyLateRule(string $status, mixed $timeIn, ?string $startTime): string
    {
        $timeIn = trim((string) $timeIn);

        if ($status !== 'present' || $timeIn === '' || ! $startTime) {
            return $status;
        }

        return $timeIn > $startTime ? 'late' : 'present';
    }

    /**
     * Whatever start a row is judged against: its section's class shift,
     * else the school-wide default. Both shapes normalised to "HH:MM" so
     * string comparison always lines up (MySQL hands shift times back as
     * "HH:MM:SS", the setting stores "HH:MM").
     */
    public static function resolveStartTime(?Shift $shift): ?string
    {
        $time = $shift?->start_time ?? Setting::current()->school_start_time;

        return $time ? substr($time, 0, 5) : null;
    }

    /**
     * @return array{ok: bool, code: string, student_id?: int, date?: string, status?: string}
     */
    public function process(mixed $admissionNumber, mixed $date, mixed $status, mixed $remarks = null, mixed $timeIn = null, mixed $timeOut = null): array
    {
        $admissionNumber = trim((string) $admissionNumber);
        if ($admissionNumber === '') {
            return ['ok' => false, 'code' => self::EMPTY];
        }

        $student = Student::where('admission_number', $admissionNumber)->first();
        if (! $student) {
            return ['ok' => false, 'code' => self::STUDENT_NOT_FOUND];
        }

        $enrollment = $this->currentYear
            ? $student->enrollments()->active()->where('academic_year_id', $this->currentYear->id)->with('section.classRoom.shift')->first()
            : null;

        if (! $enrollment) {
            return ['ok' => false, 'code' => self::NOT_ENROLLED];
        }

        if (! $this->accessibleSectionIds->contains($enrollment->section_id)) {
            return ['ok' => false, 'code' => self::FORBIDDEN_SECTION];
        }

        $parsedDate = $this->parseJalaliDate($date);
        if ($parsedDate === null || $parsedDate === false) {
            return ['ok' => false, 'code' => self::INVALID_DATE];
        }

        if ($parsedDate > JalaliDate::today()) {
            return ['ok' => false, 'code' => self::FUTURE_DATE];
        }

        $statusValue = $this->normalizeStatus($status);
        if (! $statusValue) {
            return ['ok' => false, 'code' => self::INVALID_STATUS];
        }

        $timeInValue = $this->parseTime($timeIn);
        $timeOutValue = $this->parseTime($timeOut);

        if ($timeInValue === false || $timeOutValue === false) {
            return ['ok' => false, 'code' => self::INVALID_TIME];
        }

        if ($timeInValue !== null && $timeOutValue !== null && $timeOutValue < $timeInValue) {
            return ['ok' => false, 'code' => self::TIME_OUT_BEFORE_IN];
        }

        // Times already on the record survive a timeless row: only keys
        // present in THIS row are written, so re-importing an older file
        // never wipes times recorded by scans or the roster.
        $update = [
            'section_id' => $enrollment->section_id,
            'academic_year_id' => $enrollment->academic_year_id,
            'status' => self::applyLateRule(
                $statusValue,
                $timeInValue,
                self::resolveStartTime($enrollment->section->classRoom?->shift)
            ),
            'remarks' => trim((string) $remarks) ?: null,
            'marked_by' => $this->marker->id,
        ];

        if ($timeInValue !== null) {
            $update['time_in'] = $timeInValue;
        }

        if ($timeOutValue !== null) {
            $update['time_out'] = $timeOutValue;
        }

        AttendanceRecord::updateOrCreate(
            [
                'student_id' => $student->id,
                'date' => $parsedDate,
            ],
            $update
        );

        return ['ok' => true, 'code' => 'imported', 'student_id' => $student->id, 'date' => $parsedDate, 'status' => $statusValue];
    }

    /**
     * A raw cell to a Jalali "YYYY-MM-DD" string: null for an empty cell,
     * false for a non-empty value that isn't a real Jalali date.
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
     * A status cell to one of AttendanceRecord::STATUSES, accepting the
     * English key itself or whatever label the app's three locales display
     * for it, regardless of the current locale. Null when unrecognized.
     */
    private function normalizeStatus(mixed $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        foreach (AttendanceRecord::STATUSES as $status) {
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
}
