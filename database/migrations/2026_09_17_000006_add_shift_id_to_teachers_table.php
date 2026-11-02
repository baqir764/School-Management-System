<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A teacher isn't tied to a single class (they may teach across
     * classes/shifts, or hold no homeroom at all), so their own expected
     * start time is assigned directly rather than derived from a section.
     * Nullable/SET NULL: no shift assigned falls back to
     * Setting::school_start_time, exactly like classes.shift_id.
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('employment_type')->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
