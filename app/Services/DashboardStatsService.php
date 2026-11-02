<?php

namespace App\Services;

use App\Models\User;
use App\Support\JalaliDate;
use App\Support\SchemaInfo;
use Illuminate\Support\Facades\DB;

/**
 * Computes every dashboard figure (master spec section 4) straight from
 * the database - never hard-coded. Modules that don't exist yet in this
 * phase are guarded with SchemaInfo (per-request memoized schema checks)
 * and reported as 0 rather than throwing, so the dashboard "lights up"
 * automatically as later phases add their tables.
 *
 * Performance notes (2026-11 pass): the per-request cost of this service
 * was cut by grouping the card counts into single aggregate queries
 * (one SELECT with SUM(CASE ...) instead of one COUNT per card) and by
 * computing the users-by-role chart with one GROUP BY on the pivot tables
 * instead of loading every user row into PHP. Schema checks go through
 * SchemaInfo so information_schema is hit at most once per table per
 * request. The date columns below are Shamsi strings (see
 * App\Support\JalaliDate) - compared as plain strings, never with SQL date
 * functions.
 */
class DashboardStatsService
{
    public function cards(): array
    {
        $today = JalaliDate::today();
        $students = $this->studentCounts();
        $attendance = $this->todayAttendanceCounts($today);

        return [
            'total_students' => $students['total'],
            'male_students' => $students['male'],
            'female_students' => $students['female'],
            'total_teachers' => $this->countIf('teachers'),
            'total_staff' => $this->countIf('employees'),
            'total_classes' => $this->countIf('classes'),
            // attendance_records.date is a Shamsi string - compare against
            // today's Jalali date, not Gregorian today()->toDateString().
            // For a pure Teacher dashboard, scope attendance to their own
            // homeroom section(s) only - other roles see school-wide counts.
            'today_attendance_marked' => $attendance['total'],
            'present_today' => $attendance['present'],
            'absent_today' => $attendance['absent'],
            'late_today' => $attendance['late'],
            'pending_fees_amount' => $this->pendingFeesAmount(),
            'today_payments_amount' => $this->todayPaymentsAmount(),
            'upcoming_exams' => $this->upcomingExamsCount(),
            'total_active_users' => User::where('is_active', true)->count(),
        ];
    }

    /**
     * Role name => number of (non-deleted) users holding it, in ONE query
     * over the spatie pivot tables - instead of hydrating every User model
     * and counting their roles in PHP, which loaded the whole users table
     * on every dashboard load.
     *
     * @return array<string, int>
     */
    public function usersByRole(): array
    {
        if (! SchemaInfo::hasTable('model_has_roles') || ! SchemaInfo::hasTable('roles')) {
            return [];
        }

        $query = DB::table('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->where('model_has_roles.model_type', User::class)
            ->groupBy('roles.name')
            ->select('roles.name', DB::raw('count(*) as aggregate'));

        // Match the previous Eloquent-based behaviour of ignoring
        // soft-deleted user accounts.
        if (SchemaInfo::hasTable('users') && SchemaInfo::hasColumn('users', 'deleted_at')) {
            $query->join('users', function ($join) {
                $join->on('users.id', '=', 'model_has_roles.model_id')
                    ->whereNull('users.deleted_at');
            });
        }

        return $query->get()
            ->sortByDesc('aggregate')
            ->pluck('aggregate', 'name')
            ->map(fn ($count) => (int) $count)
            ->toArray();
    }

    /**
     * Exams whose start date hasn't passed yet - "upcoming" rather than
     * every exam ever created, which is what a plain countIf() would give.
     */
    private function upcomingExamsCount(): int
    {
        if (! SchemaInfo::hasTable('exams') || ! SchemaInfo::hasColumn('exams', 'start_date')) {
            return 0;
        }

        // exams.start_date is a Shamsi string (App\Support\JalaliDate) -
        // plain string comparison against today's Jalali date, not a SQL
        // date function (which would assume a Gregorian DATE column).
        return DB::table('exams')->where('start_date', '>=', JalaliDate::today())->count();
    }

    /**
     * Total outstanding balance (invoiced minus paid) across every fee
     * invoice for the currently active academic year - mirrors the scope
     * FeeInvoiceController::index()/dues() default to. Status/balance are
     * never stored per-invoice (see FeeInvoice::balance()), but a global
     * sum-of-invoiced minus sum-of-paid gives the same total without
     * loading every invoice into PHP.
     */
    private function pendingFeesAmount(): float
    {
        if (! SchemaInfo::hasTable('fee_invoices') || ! SchemaInfo::hasTable('fee_payments')) {
            return 0;
        }

        $currentYearId = SchemaInfo::hasTable('academic_years') && SchemaInfo::hasColumn('academic_years', 'is_active')
            ? DB::table('academic_years')->where('is_active', true)->value('id')
            : null;

        if (! $currentYearId) {
            return 0;
        }

        $invoiced = (float) DB::table('fee_invoices')->where('academic_year_id', $currentYearId)->sum('amount');
        $paid = (float) DB::table('fee_payments')
            ->join('fee_invoices', 'fee_invoices.id', '=', 'fee_payments.fee_invoice_id')
            ->where('fee_invoices.academic_year_id', $currentYearId)
            ->sum('fee_payments.amount');

        return max(0.0, round($invoiced - $paid, 2));
    }

    private function todayPaymentsAmount(): float
    {
        if (! SchemaInfo::hasTable('fee_payments') || ! SchemaInfo::hasColumn('fee_payments', 'payment_date')) {
            return 0;
        }

        // Plain string comparison against today's Jalali date, not a SQL
        // date function (which would assume a Gregorian DATE column) -
        // payment_date is stored as a Jalali "YYYY-MM-DD" string.
        return (float) DB::table('fee_payments')->where('payment_date', JalaliDate::today())->sum('amount');
    }

    private function countIf(string $table, array $where = []): int
    {
        if (! SchemaInfo::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        foreach ($where as $column => $value) {
            if (SchemaInfo::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return $query->count();
    }

    /**
     * Total / male / female student counts in a single aggregate query
     * (previously three separate COUNT queries).
     *
     * @return array{total: int, male: int, female: int}
     */
    private function studentCounts(): array
    {
        $empty = ['total' => 0, 'male' => 0, 'female' => 0];

        if (! SchemaInfo::hasTable('students')) {
            return $empty;
        }

        $hasGender = SchemaInfo::hasColumn('students', 'gender');

        $row = DB::table('students')
            ->selectRaw('count(*) as total')
            ->when($hasGender, fn ($q) => $q
                ->selectRaw("sum(case when gender = 'male' then 1 else 0 end) as male")
                ->selectRaw("sum(case when gender = 'female' then 1 else 0 end) as female"))
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'male' => (int) ($row->male ?? 0),
            'female' => (int) ($row->female ?? 0),
        ];
    }

    /**
     * Today's marked/present/absent/late attendance counts in a single
     * aggregate query (previously four separate COUNT queries), optionally
     * scoped to a pure Teacher's own homeroom section(s).
     *
     * @return array{total: int, present: int, absent: int, late: int}
     */
    private function todayAttendanceCounts(string $today): array
    {
        $empty = ['total' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];

        if (! SchemaInfo::hasTable('attendance_records')
            || ! SchemaInfo::hasColumn('attendance_records', 'date')
            || ! SchemaInfo::hasColumn('attendance_records', 'status')) {
            return $empty;
        }

        $query = DB::table('attendance_records')->where('date', $today);

        // Pure Teacher sees only their own homeroom section(s).
        if ($this->isTeacherDashboard()) {
            $ids = $this->teacherSectionIds();
            if (empty($ids)) {
                return $empty;
            }
            $query->whereIn('section_id', $ids);
        }

        $row = $query
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when status = 'present' then 1 else 0 end) as present")
            ->selectRaw("sum(case when status = 'absent' then 1 else 0 end) as absent")
            ->selectRaw("sum(case when status = 'late' then 1 else 0 end) as late")
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'present' => (int) ($row->present ?? 0),
            'absent' => (int) ($row->absent ?? 0),
            'late' => (int) ($row->late ?? 0),
        ];
    }

    private function isTeacherDashboard(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return $user->hasRole('Teacher')
            && ! $user->hasAnyRole(['Super Administrator', 'School Administrator']);
    }

    private function teacherSectionIds(): array
    {
        $user = auth()->user();
        if (! $user) {
            return [];
        }

        // Avoid lazy-loading $user->teacher under Model::shouldBeStrict().
        $teacher = \App\Models\Teacher::where('user_id', $user->id)->first();
        if (! $teacher) {
            return [];
        }

        return $teacher->homeroomSections()->pluck('id')->all();
    }
}
