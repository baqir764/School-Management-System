<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 8 (Section Setup). "class_teacher_id" is the
     * homeroom/class teacher for this section - lives here rather than on
     * "classes" because a class teacher is assigned per section, not per
     * grade level.
     */
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('name'); // e.g. "A", "B"
            $table->foreignId('class_teacher_id')->nullable()->constrained('teachers')->nullOnDelete();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('room_number')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['class_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
