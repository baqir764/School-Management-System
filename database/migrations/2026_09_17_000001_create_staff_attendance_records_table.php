<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily presence for teachers and employees, marked from the same QR
     * scan page as student attendance. One row per person per calendar
     * date - re-scanning just confirms. The attendee is polymorphic
     * ('teacher' | 'employee', see the morph map in AppServiceProvider),
     * mirroring how SalaryPayment::payee() already works for payroll.
     * 'date' is a Jalali "YYYY-MM-DD" string in a date column, exactly
     * like attendance_records.
     */
    public function up(): void
    {
        Schema::create('staff_attendance_records', function (Blueprint $table) {
            $table->id();
            $table->string('attendee_type');
            $table->unsignedBigInteger('attendee_id');
            $table->date('date');
            $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
            $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            // Deliberately NO soft deletes (unlike attendance_records): a
            // trashed row would keep occupying the unique
            // attendee+date key and break re-scans with a 1062, while the
            // audit log already preserves the delete trail.

            $table->unique(['attendee_type', 'attendee_id', 'date']);
            $table->index(['date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_attendance_records');
    }
};
