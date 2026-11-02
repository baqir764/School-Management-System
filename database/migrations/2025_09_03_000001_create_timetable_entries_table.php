<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 15 (Timetable). One row = "this section has this
     * subject, taught by this teacher, on this day, in this period."
     * day_of_week follows ISO-8601 (1 = Monday .. 7 = Sunday) so schools on
     * either a Sat-Thu or Mon-Fri week can use the same column.
     */
    public function up(): void
    {
        Schema::create('timetable_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->foreignId('period_id')->constrained('periods')->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 1 (Mon) - 7 (Sun)
            $table->foreignId('subject_id')->constrained('subjects')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('teachers')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // A section can only have one subject in a given period on a
            // given day.
            $table->unique(['section_id', 'period_id', 'day_of_week'], 'timetable_section_slot_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('timetable_entries');
    }
};
