<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 11 (Student Registration). Deliberately does NOT
     * carry class/section/roll_number - that belongs to "enrollments" so a
     * student's placement history across academic years is preserved
     * instead of overwritten (spec section 14). "admission_number" is
     * generated once at creation and never changes.
     */
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('admission_number')->unique(); // e.g. STU-2026-0001
            $table->string('first_name');
            $table->string('last_name');
            $table->string('father_name')->nullable();
            $table->string('grandfather_name')->nullable();
            $table->enum('gender', ['male', 'female']);
            $table->date('date_of_birth')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('national_id')->nullable();
            $table->string('nationality')->default('Afghan');
            $table->string('religion')->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->string('address')->nullable();
            $table->string('province')->nullable();
            $table->string('district')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('photo_path')->nullable();
            $table->date('admission_date');
            $table->enum('status', ['active', 'inactive', 'graduated', 'transferred', 'expelled'])->default('active');
            $table->string('previous_school')->nullable();
            $table->text('medical_notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
