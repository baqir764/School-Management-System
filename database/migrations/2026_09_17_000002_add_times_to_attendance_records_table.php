<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily check-in/out times on student attendance, so late arrivals
     * can be analysed against the school start time (settings table)
     * instead of relying on memory. Both nullable: old rows and
     * scan/import flows that carry no time keep working untouched.
     * Stored as TIME (HH:MM:SS); the app reads/writes "HH:MM".
     */
    public function up(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->time('time_in')->nullable()->after('status');
            $table->time('time_out')->nullable()->after('time_in');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_records', function (Blueprint $table) {
            $table->dropColumn(['time_in', 'time_out']);
        });
    }
};
