<?php

use App\Support\JalaliDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 5 (final phase) of the Shamsi conversion (see the Phase 2
     * migration, 2025_09_04_000006, for the full rationale):
     * employees.joining_date and salary_payments.payment_date - widened
     * from DATE to VARCHAR(10) and rewritten in place, same as every
     * other phase.
     *
     * salary_payments.salary_month is different: it was a DATE normalized
     * to the 1st of the Gregorian calendar month it covers, but a Jalali
     * month doesn't line up with a Gregorian one, so pretending it starts
     * on "the 1st" the way the old column did would be meaningless. It
     * becomes a genuine month-only Jalali "YYYY-MM" string (VARCHAR(7),
     * see JalaliDate::monthYearDisplay()) - each existing value is
     * converted to its Jalali equivalent and truncated to just the
     * year-month.
     *
     * Each column is guarded with isDateColumn()/isDateColumn() checks so
     * this migration can be safely re-run after a partial failure (MySQL
     * DDL auto-commits outside any transaction, so a failure partway
     * through leaves some columns already converted) without
     * double-converting an already-Jalali value as if it were still
     * Gregorian.
     */
    public function up(): void
    {
        if ($this->isDateColumn('employees', 'joining_date')) {
            $this->widenToVarchar('employees', 'joining_date', 10, true);
            $this->rewriteColumn('employees', 'joining_date', fn (string $value) => JalaliDate::fromGregorian($value));
        }

        if ($this->isDateColumn('salary_payments', 'payment_date')) {
            $this->widenToVarchar('salary_payments', 'payment_date', 10, false);
            $this->rewriteColumn('salary_payments', 'payment_date', fn (string $value) => JalaliDate::fromGregorian($value));
        }

        if ($this->isDateColumn('salary_payments', 'salary_month')) {
            // Two-step width change: MySQL casts every existing value to
            // fit the new column type as part of ALTER TABLE MODIFY, and
            // at this point the existing value is still a 10-character
            // Gregorian "YYYY-MM-DD" string - which doesn't fit directly
            // into the final VARCHAR(7) (this is exactly what failed the
            // first time this migration ran: "Data too long for column
            // 'salary_month'"). Widen to VARCHAR(10) first - enough to
            // hold the Gregorian string untouched - rewrite the data down
            // to its 7-character Jalali "YYYY-MM" form, then shrink the
            // column once every value already fits.
            $this->widenToVarchar('salary_payments', 'salary_month', 10, false);
            $this->rewriteColumn('salary_payments', 'salary_month', fn (string $value) => substr(JalaliDate::fromGregorian($value), 0, 7));
            $this->widenToVarchar('salary_payments', 'salary_month', 7, false);
        }
    }

    public function down(): void
    {
        if (! $this->isDateColumn('employees', 'joining_date')) {
            $this->rewriteColumn('employees', 'joining_date', fn (string $value) => JalaliDate::toGregorian($value)?->format('Y-m-d'));
            DB::statement('ALTER TABLE `employees` MODIFY `joining_date` DATE NULL');
        }

        if (! $this->isDateColumn('salary_payments', 'payment_date')) {
            $this->rewriteColumn('salary_payments', 'payment_date', fn (string $value) => JalaliDate::toGregorian($value)?->format('Y-m-d'));
            DB::statement('ALTER TABLE `salary_payments` MODIFY `payment_date` DATE NOT NULL');
        }

        if (! $this->isDateColumn('salary_payments', 'salary_month')) {
            // Same two-step width change in reverse: the column is only
            // VARCHAR(7) at this point (holding "YYYY-MM"), but the
            // rewritten value is a full 10-character "YYYY-MM-DD" - widen
            // first, then shrink back down to DATE once rewritten.
            //
            // Approximate inverse only: a month-only Jalali string has no
            // day component, so "day 1" is assumed before converting
            // back, then normalized to the 1st of whichever Gregorian
            // month that falls in - this won't perfectly restore the
            // original value (the two calendars' months don't align),
            // which is expected for a rollback of a field that's
            // intentionally month-only going forward.
            $this->widenToVarchar('salary_payments', 'salary_month', 10, false);
            $this->rewriteColumn('salary_payments', 'salary_month', function (string $value) {
                $gregorian = JalaliDate::toGregorian($value.'-01');

                return $gregorian?->modify('first day of this month')->format('Y-m-d');
            });
            DB::statement('ALTER TABLE `salary_payments` MODIFY `salary_month` DATE NOT NULL');
        }
    }

    /**
     * True only while $column is still its original DATE type - the
     * signal that this column hasn't been converted yet (used to make
     * up()/down() safe to re-run after a partial failure, since MySQL DDL
     * auto-commits and can't be rolled back with the rest of a failed
     * migration).
     */
    private function isDateColumn(string $table, string $column): bool
    {
        $row = DB::selectOne(
            'SELECT DATA_TYPE AS type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $row && strtolower($row->type) === 'date';
    }

    private function widenToVarchar(string $table, string $column, int $length, bool $nullable): void
    {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR({$length}) {$null}");
    }

    private function rewriteColumn(string $table, string $column, \Closure $convert): void
    {
        DB::table($table)
            ->select(['id', $column])
            ->whereNotNull($column)
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($table, $column, $convert) {
                foreach ($rows as $row) {
                    $converted = $convert($row->{$column});
                    if ($converted !== null && $converted !== $row->{$column}) {
                        DB::table($table)->where('id', $row->id)->update([$column => $converted]);
                    }
                }
            });
    }
};
