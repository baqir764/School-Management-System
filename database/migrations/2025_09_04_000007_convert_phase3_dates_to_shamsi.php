<?php

use App\Support\JalaliDate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Phase 3 of the Shamsi conversion (see the Phase 2 migration,
     * 2025_09_04_000006, for the full rationale): attendance_records.date,
     * exams.start_date/end_date, exam_subjects.exam_date - widened from
     * DATE to VARCHAR(10) and every existing value rewritten from
     * Gregorian to its Jalali equivalent in place.
     *
     * [table, column, nullable]
     */
    private const COLUMNS = [
        ['attendance_records', 'date', false],
        ['exams', 'start_date', false],
        ['exams', 'end_date', false],
        ['exam_subjects', 'exam_date', true],
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
