<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Performance pass (2026-11): add indexes on the columns the app filters,
 * sorts or joins on that are NOT already covered.
 *
 * Foreign-key columns are already indexed implicitly (InnoDB creates an
 * index for every foreign key), and the unique constraints from the
 * original migrations cover their own leftmost columns - so this
 * migration only adds indexes for plain columns used in WHERE / ORDER BY:
 *
 * - attendance_records.date        - dashboard "today" cards + history
 * - attendance_records.section_id+date - the daily roster lookup
 * - fee_payments.payment_date      - dashboard "today's payments"
 * - fee_invoices.due_date          - dues report
 * - salary_payments.salary_month / payment_date - payroll register
 * - exams.start_date / is_published- upcoming-exams card + exam lists
 * - students.status / gender       - dashboard cards + list filters
 * - teachers/employees.status      - staff rosters
 * - enrollments.status             - "active enrollment" lookups everywhere
 * - academic_years/classes/subjects/fee_structures/shifts .is_active
 * - users.is_active                - dashboard active-users card
 * - announcements.is_published / created_at
 * - notifications.read_at          - topbar unread badge (every page)
 * - parent_student.student_id      - "which parents does this student have"
 *
 * Every statement is guarded (hasTable + hasIndex) so the migration is
 * safe to run on any database state, including ones where a later
 * migration renamed or dropped something.
 */
return new class extends Migration
{
    /**
     * @var array<string, array<int, array{columns: array<int, string>, name: string}>>
     */
    private array $indexes = [
        'students' => [
            [['status'], 'students_status_index'],
            [['gender'], 'students_gender_index'],
        ],
        'teachers' => [
            [['status'], 'teachers_status_index'],
        ],
        'employees' => [
            [['status'], 'employees_status_index'],
        ],
        'academic_years' => [
            [['is_active'], 'academic_years_is_active_index'],
        ],
        'classes' => [
            [['is_active'], 'classes_is_active_index'],
        ],
        'subjects' => [
            [['is_active'], 'subjects_is_active_index'],
        ],
        'fee_structures' => [
            [['is_active'], 'fee_structures_is_active_index'],
        ],
        'shifts' => [
            [['is_active'], 'shifts_is_active_index'],
        ],
        'enrollments' => [
            [['status'], 'enrollments_status_index'],
        ],
        'attendance_records' => [
            [['date'], 'attendance_records_date_index'],
            [['section_id', 'date'], 'attendance_records_section_date_index'],
        ],
        'fee_invoices' => [
            [['due_date'], 'fee_invoices_due_date_index'],
        ],
        'fee_payments' => [
            [['payment_date'], 'fee_payments_payment_date_index'],
        ],
        'salary_payments' => [
            [['salary_month'], 'salary_payments_salary_month_index'],
            [['payment_date'], 'salary_payments_payment_date_index'],
        ],
        'exams' => [
            [['start_date'], 'exams_start_date_index'],
            [['is_published'], 'exams_is_published_index'],
        ],
        'announcements' => [
            [['is_published'], 'announcements_is_published_index'],
            [['created_at'], 'announcements_created_at_index'],
        ],
        'notifications' => [
            [['read_at'], 'notifications_read_at_index'],
        ],
        'users' => [
            [['is_active'], 'users_is_active_index'],
        ],
        'parent_student' => [
            [['student_id'], 'parent_student_student_id_index'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();

            foreach ($indexes as [$columns, $name]) {
                if (in_array($name, $existing, true)) {
                    continue;
                }

                // A column may have been dropped by a later migration - never
                // fail the whole performance pass over one missing column.
                $missing = array_filter($columns, fn (string $column) => ! Schema::hasColumn($table, $column));
                if ($missing) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
                    $blueprint->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $existing = collect(Schema::getIndexes($table))->pluck('name')->all();

            foreach ($indexes as [$columns, $name]) {
                if (! in_array($name, $existing, true)) {
                    continue;
                }

                Schema::table($table, function (Blueprint $blueprint) use ($name) {
                    $blueprint->dropIndex($name);
                });
            }
        }
    }
};
