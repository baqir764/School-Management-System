<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The school day's start ("HH:MM", nullable) that late arrivals are
     * analysed against: a "present" mark with a time_in past this time is
     * stored as late. Empty means no automatic analysis - statuses stay
     * exactly as marked. Editable on the School Settings page.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('school_start_time', 5)->nullable()->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('school_start_time');
        });
    }
};
