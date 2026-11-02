<?php

use App\Support\JalaliDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Converts every Phase 2 date column (students, teachers,
     * academic_years, enrollments) from a native MySQL DATE to a plain
     * "YYYY-MM-DD" Shamsi (Jalali/Afghan Solar Hijri) string - see
     * App\Support\JalaliDate for why: MySQL's DATE type can't hold a
     * Jalali year/month/day (it validates against the Gregorian
     * calendar and rejects anything else), so from here on these
     * columns are just VARCHAR(10) text that happens to sort/compare
     * correctly because it's always zero-padded, the same reasoning
     * that makes ISO Gregorian strings sortable.
     *
     * Existing data is preserved, not dropped: each column is widened to
     * VARCHAR first - MySQL keeps the Gregorian value as its
     * "YYYY-MM-DD" text representation across that ALTER (the same
     * conversion CAST(col AS CHAR) would give) - and every existing row
     * is then rewritten in place from that Gregorian text to its Jalali
     * equivalent. down() reverses both steps, converting each Jalali
     * string back to Gregorian text before restoring the DATE type, so
     * rolling back doesn't silently lose or corrupt historical dates.
     *
     * [table, column, nullable] - matches each column's original
     * definition in its create_*_table migration exactly.
     */
    private const COLUMNS = [
        ['students', 'date_of_birth', true],
        ['students', 'admission_date', false],
        ['teachers', 'date_of_birth', true],
        ['teachers', 'joining_date', true],
        ['academic_years', 'start_date', false],
        ['academic_years', 'end_date', false],
        ['enrollments', 'enrollment_date', false],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as [$table, $column, $nullable]) {
            $this->widenToVarchar($table, $column, $nullable);
            $this->rewriteColumn($table, $column, fn (string $value) => JalaliDate::fromGregorian($value));
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as [$table, $column, $nullable]) {
            $this->rewriteColumn($table, $column, fn (string $value) => JalaliDate::toGregorian($value)?->format('Y-m-d'));

            $null = $nullable ? 'NULL' : 'NOT NULL';
            DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` DATE {$null}");
        }
    }

    private function widenToVarchar(string $table, string $column, bool $nullable): void
    {
        $null = $nullable ? 'NULL' : 'NOT NULL';
        DB::statement("ALTER TABLE `{$table}` MODIFY `{$column}` VARCHAR(10) {$null}");
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
