<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Master spec section 15 (Timetable). A "period" is a daily time slot
     * (e.g. "Period 1", 08:00-08:45) shared across the whole school; the
     * actual per-section, per-day schedule lives on timetable_entries.
     */
    public function up(): void
    {
        Schema::create('periods', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "Period 1"
            $table->time('start_time');
            $table->time('end_time');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periods');
    }
};
