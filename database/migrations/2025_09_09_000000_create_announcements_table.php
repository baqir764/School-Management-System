<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Announcements as database notifications for managers/staff to
     * broadcast to parents, teachers and students (see
     * App\Notifications\AnnouncementNotification). The notifications table
     * already exists (standard Laravel) - this is the authored content that
     * gets fanned out into that table via ->notify() on publish.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('body');
            // Target roles as JSON array of role names (e.g. ["Teacher","Parent"]).
            // Empty/null = "all active users". Kept as JSON not enum so new
            // roles never need a schema change.
            $table->json('target_roles')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
