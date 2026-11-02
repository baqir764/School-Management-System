<?php

namespace App\Http\Controllers;

use App\Models\FeeInvoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

/**
 * Every user's own notification inbox (currently just
 * App\Notifications\FeeReminderNotification, but this stays generic -
 * anything else that later calls ->notify() lands here too). Every action
 * is scoped through auth()->user()->notifications() rather than a route-
 * bound model, so one user can never mark another user's notification as
 * read by guessing its id.
 *
 * Fee reminders are special: they represent a live, still-unpaid debt, so
 * they stay unread until the fee is fully paid in the system (recording
 * the final payment auto-marks them read - see
 * Fees\FeePaymentController::store()). Manually dismissing one early is
 * refused with an explanatory message.
 */
class NotificationController extends Controller
{
    public function index(): View
    {
        return view('notifications.index', [
            'notifications' => auth()->user()->notifications()->paginate(20),
        ]);
    }

    public function read(string $notification): RedirectResponse
    {
        $record = auth()->user()->notifications()->where('id', $notification)->firstOrFail();

        if (self::isLockedFeeReminder($record)) {
            return back()->with('error', __('app.fee_reminder_locked_until_paid'));
        }

        $record->markAsRead();

        return back();
    }

    public function readAll(): RedirectResponse
    {
        $locked = 0;

        foreach (auth()->user()->unreadNotifications as $notification) {
            if (self::isLockedFeeReminder($notification)) {
                $locked++;
                continue;
            }
            $notification->markAsRead();
        }

        if ($locked > 0) {
            return back()->with('error', __('app.fee_reminder_locked_until_paid'));
        }

        return back();
    }

    /**
     * A fee-reminder notification is "locked" (cannot be manually marked
     * read) while its invoice still has an outstanding balance. Anything
     * else - announcements, reminders for fully-paid or deleted invoices -
     * is never locked and behaves exactly as before.
     */
    public static function isLockedFeeReminder(DatabaseNotification $notification): bool
    {
        $invoiceId = $notification->data['fee_invoice_id'] ?? null;

        if (! $invoiceId) {
            return false;
        }

        $invoice = FeeInvoice::query()->with('payments')->find($invoiceId);

        if (! $invoice) {
            return false;
        }

        return $invoice->balance() > 0;
    }
}
