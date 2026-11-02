<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which subjects an exam covers, and the marks scale for each - kept
     * separate from Subject's own full_marks/pass_marks defaults since a
     * particular exam (e.g. a short quiz) may use a smaller scale.
     */
    public function up(): void
    {
        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->date('exam_date')->nullable();
            $table->unsignedInteger('full_marks')->default(100);
            $table->unsignedInteger('pass_marks')->default(40);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['exam_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_subjects');
    }
};
