<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 7 (Class Setup). "classes" holds the grade/class
     * definition only (e.g. "Grade 1"); per-teacher, per-room, per-capacity
     * detail lives one level down on "sections" (created after "teachers"),
     * since a class is normally split into multiple sections.
     */
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Grade 1", "Kindergarten"
            $table->unsignedInteger('numeric_level'); // ordering/promotion sequence
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique('numeric_level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
