<?php

namespace App\Services;

use App\Models\User;
use App\Support\JalaliDate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Computes every dashboard figure (master spec section 4) straight from
 * the database - never hard-coded. Modules that don't exist yet in this
 * phase are guarded with Schema::hasTable() and reported as 0 rather than
 * throwing, so the dashboard "lights up" automatically as later phases add
 * their tables without any changes needed here beyond removing the guard.
 */
class DashboardStatsService
{
    public function cards(): array
    {
        return [
            'total_students' => $this->countIf('students'),
            'male_students' => $this->countIf('students', ['gender' => 'male']),
            'female_students' => $this->countIf('students', ['gender' => 'female']),
            'total_teachers' => $this->countIf('teachers'),
            'total_staff' => $this->countIf('employees'),
            'total_classes' => $this->countIf('classes'),
            // attendance_records.date is a Shamsi string (see
            // App\Support\JalaliDate) - compare against today's Jalali
            // date, not Gregorian today()->toDateString().
            // For pure Teacher dashboard, scope attendance to their own
            // homeroom section(s) only - other roles see school-wide counts.
            'today_attendance_marked' => $this->attendanceCount(['date' => JalaliDate::today()]),
            'present_today' => $this->attendanceCount(['date' => JalaliDate::today(), 'status' => 'present']),
            'absent_today' => $this->attendanceCount(['date' => JalaliDate::today(), 'status' => 'absent']),
            'late_today' => $this->attendanceCount(['date' => JalaliDate::today(), 'status' => 'late']),
            'pending_fees_amount' => $this->pendingFeesAmount(),
            'today_payments_amount' => $this->todayPaymentsAmount(),
            'upcoming_exams' => $this->upcomingExamsCount(),
            'total_active_users' => User::where('is_active', true)->count(),
        ];
    }

    public function usersByRole(): array
    {
        return User::query()
            ->withCount('roles')
            ->get()
            ->flatMap(fn ($user) => $user->getRoleNames())
            ->countBy()
            ->sortDesc()
            ->toArray();
    }

    public function recentAdmissions(int $limit = 5): array
    {
        return Schema::hasTable('students')
            ? DB::table('students')->orderByDesc('created_at')->limit($limit)->get()->toArray()
            : [];
    }

    public function recentPayments(int $limit = 5): array
    {
        return Schema::hasTable('payments')
            ? DB::table('payments')->orderByDesc('created_at')->limit($limit)->get()->toArray()
            : [];
    }

    public function recentAnnouncements(int $limit = 5): array
    {
        if (! Schema::hasTable('announcements')) {
            return [];
        }

        // Announcements table has published_at (nullable) + created_at -
        // there is no publish_date column. Order by created_at so drafts
        // (published_at = null) sort deterministically, and exclude
        // soft-deleted rows (DB::table bypasses the SoftDeletes scope).
        $query = DB::table('announcements');

        if (Schema::hasColumn('announcements', 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        $orderColumn = Schema::hasColumn('announcements', 'created_at') ? 'created_at' : 'id';

        return $query->orderByDesc($orderColumn)->limit($limit)->get()->toArray();
    }

    /**
     * Exams whose start date hasn't passed yet - "upcoming" rather than
     * every exam ever created, which is what a plain countIf() would give.
     */
    private function upcomingExamsCount(): int
    {
        if (! Schema::hasTable('exams')) {
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
        if (! Schema::hasTable('fee_invoices') || ! Schema::hasTable('fee_payments')) {
            return 0;
        }

        $currentYearId = Schema::hasTable('academic_years')
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
        if (! Schema::hasTable('fee_payments')) {
            return 0;
        }

        // Plain string comparison against today's Jalali date, not a SQL
        // date function (which would assume a Gregorian DATE column) -
        // payment_date is stored as a Jalali "YYYY-MM-DD" string.
        return (float) DB::table('fee_payments')->where('payment_date', JalaliDate::today())->sum('amount');
    }

    private function countIf(string $table, array $where = []): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);

        foreach ($where as $column => $value) {
            if (Schema::hasColumn($table, $column)) {
                $query->where($column, $value);
            }
        }

        return $query->count();
    }

    private function attendanceCount(array $where = []): int
    {
        if (! Schema::hasTable('attendance_records')) {
            return 0;
        }

        $query = DB::table('attendance_records');

        foreach ($where as $column => $value) {
            if (Schema::hasColumn('attendance_records', $column)) {
                $query->where($column, $value);
            }
        }

        // Pure Teacher sees only their own homeroom section(s).
        if ($this->isTeacherDashboard()) {
            $ids = $this->teacherSectionIds();
            if (empty($ids)) {
                return 0;
            }
            $query->whereIn('section_id', $ids);
        }

        return $query->count();
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
