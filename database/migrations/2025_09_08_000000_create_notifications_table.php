<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Laravel's standard database-notifications schema. App\Models\User
     * already has the Notifiable trait wired up (it was there from the
     * start, unused until now), so this is the one missing piece needed
     * to actually deliver a notification - e.g.
     * App\Notifications\FeeReminderNotification, sent to a parent's
     * linked user account from FeeInvoiceController::sendReminders() -
     * anywhere in the app. DashboardStatsService's Schema::hasTable()
     * guards on not-yet-built tables are the same reasoning in reverse:
     * this table is deliberately just the plain Laravel default shape
     * (not a custom one) so any future ->notify() call anywhere in the
     * app works without another migration.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
