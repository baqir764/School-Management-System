<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 14 (Promotion/Enrollment history). One row per
     * student per academic year - this is what actually places a student
     * into a class/section for a given year, so re-enrolling for a new
     * year (promotion) adds a new row instead of overwriting the old one.
     */
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->string('roll_number', 20)->nullable();
            $table->date('enrollment_date');
            $table->enum('status', ['active', 'promoted', 'repeated', 'transferred_out', 'withdrawn'])->default('active');
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // A student may only have one enrollment record per academic year.
            $table->unique(['student_id', 'academic_year_id']);
            // Roll numbers are unique within a section for a given year.
            $table->unique(['academic_year_id', 'section_id', 'roll_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
