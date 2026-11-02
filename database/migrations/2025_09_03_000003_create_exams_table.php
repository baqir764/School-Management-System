<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 17 (Exams). An exam belongs to one class for one
     * academic year (e.g. "Grade 3 - Mid-Term 2026"); which subjects it
     * covers, and each one's marks scale, live on exam_subjects.
     * is_published gates whether results are visible outside staff/admin
     * views - kept here for the student/parent portal in a later phase.
     */
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('name'); // e.g. "Mid-Term Exam"
            $table->enum('term_type', ['quiz', 'mid_term', 'final', 'other'])->default('final');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_published')->default(false);
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exams');
    }
};
