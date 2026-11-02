<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 10 (Subject Management). "class_id" ties a
     * subject to one grade level (e.g. "Mathematics - Grade 1" is a
     * different row from "Mathematics - Grade 2"), which keeps exam/marks
     * modules in later phases simple; nullable for subjects offered across
     * every class (e.g. an elective). "teacher_id" is the default subject
     * teacher and can be left unassigned.
     */
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique(); // e.g. MATH-1
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->unsignedInteger('full_marks')->default(100);
            $table->unsignedInteger('pass_marks')->default(40);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};
