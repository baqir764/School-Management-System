<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot for the many-to-many parent<->student relationship (a parent
     * can have several children enrolled; a student can have more than one
     * linked guardian). "relationship" records how this specific parent
     * relates to this specific student (Father/Mother/Guardian/Other).
     */
    public function up(): void
    {
        Schema::create('parent_student', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->constrained('parents')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('relationship', ['Father', 'Mother', 'Guardian', 'Other'])->default('Guardian');
            $table->boolean('is_primary_contact')->default(false);
            $table->timestamps();

            $table->unique(['parent_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_student');
    }
};
