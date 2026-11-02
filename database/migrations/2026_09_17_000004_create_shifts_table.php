<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shifts (دوره): a school running more than one daily session (e.g.
     * Grades 1-4 in the morning, Grades 5-6 in the afternoon) needs its own
     * start time per session so "late" is judged against the right clock -
     * a single school-wide start_time (settings table) can't express that.
     * A shift is assigned to a Class (every section of that class inherits
     * it) and, independently, directly to a Teacher or Employee, since
     * staff don't belong to a single class. Setting::school_start_time
     * remains the fallback for anyone/anything with no shift assigned, so
     * existing behaviour is unchanged until shifts are actually set up.
     */
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->time('start_time');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
