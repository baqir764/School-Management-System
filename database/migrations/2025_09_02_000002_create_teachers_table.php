<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 9 (Teacher Management). "user_id" is nullable and
     * unique-when-not-null: a teacher record can exist purely as an HR/staff
     * record, or be linked to a login account (role "Teacher") so the person
     * can sign in. Linking/unlinking is a deliberate admin action, not
     * automatic, since not every teacher needs a login.
     */
    public function up(): void
    {
        Schema::create('teachers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('employee_code')->unique(); // e.g. TCH-0001
            $table->string('first_name');
            $table->string('last_name');
            $table->string('father_name')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('national_id')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('qualification')->nullable();
            $table->string('specialization')->nullable();
            $table->date('joining_date')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'contract'])->default('full_time');
            $table->enum('status', ['active', 'inactive', 'terminated'])->default('active');
            $table->string('photo_path')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
