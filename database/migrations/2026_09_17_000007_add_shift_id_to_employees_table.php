<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Non-teaching staff have no class at all to inherit a shift from, so
     * (like teachers.shift_id) it's assigned directly on the employee
     * record. Nullable/SET NULL: no shift assigned falls back to
     * Setting::school_start_time.
     */
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('shift_id')->nullable()->after('position')->constrained('shifts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shift_id');
        });
    }
};
