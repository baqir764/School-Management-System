<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Parent-to-teacher messaging (see AnnouncementController::store()):
     * a parent addresses specific teachers (their children's teachers),
     * not a whole role. target_user_ids holds those teachers' user IDs
     * as a JSON array; null/empty keeps the previous role-based behavior
     * untouched. Purely additive - no existing row is modified.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->json('target_user_ids')->nullable()->after('target_roles');
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropColumn('target_user_ids');
        });
    }
};
