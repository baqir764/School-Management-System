<?php

namespace App\Notifications;

use App\Models\Announcement;
use Illuminate\Notifications\Notification;

/**
 * Database-only broadcast of an Announcement to the selected roles
 * (parents / teachers / students / staff). Rendered in the bell dropdown
 * and in notifications.index via _message.blade.php's announcement branch.
 * No mail channel - matches FeeReminderNotification's pattern.
 */
class AnnouncementNotification extends Notification
{
    public function __construct(private readonly Announcement $announcement)
    {
    }

    /**
     * @return array<int, string>
     */
    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(mixed $notifiable): array
    {
        $a = $this->announcement;

        return [
            'announcement_id' => $a->id,
            'title' => $a->title,
            'excerpt' => mb_strimwidth($a->body, 0, 140, '…'),
            'body' => $a->body,
        ];
    }
}
