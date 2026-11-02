<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every section of a class inherits that class's shift, so attendance
     * "late" detection for its students is judged against the shift's own
     * start time instead of the single school-wide default. Nullable and
     * SET NULL on delete: a class with no shift assigned (or whose shift
     * gets deleted) simply falls back to Setting::school_start_time - no
     * data loss, no broken class.
     */
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('numeric_level')->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
