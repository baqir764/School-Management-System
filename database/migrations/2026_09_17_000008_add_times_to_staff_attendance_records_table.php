<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check-in time for teachers/employees, exactly like
     * attendance_records.time_in/time_out - lets staff "late" be judged
     * against their own shift's start time (or the school-wide default)
     * instead of always requiring a human to pick "late" by hand. Both
     * nullable: manual roster rows with no time keep working untouched.
     */
    public function up(): void
    {
        Schema::table('staff_attendance_records', function (Blueprint $table) {
            $table->time('time_in')->nullable()->after('status');
            $table->time('time_out')->nullable()->after('time_in');
        });
    }

    public function down(): void
    {
        Schema::table('staff_attendance_records', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out']);
        });
    }
};
