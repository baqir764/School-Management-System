<?php

namespace App\Notifications;

use App\Models\FeeInvoice;
use App\Support\JalaliDate;
use Illuminate\Notifications\Notification;

/**
 * A short in-app reminder about one outstanding fee invoice, sent to a
 * parent/guardian's linked user account (see
 * FeeInvoiceController::sendReminders()). Database-only for now (no mail/
 * SMS channel wired up) - it surfaces via the bell dropdown in the topbar
 * (resources/views/components/topbar.blade.php), which every authenticated
 * user already gets through App\Models\User's existing Notifiable trait.
 *
 * Deliberately does NOT store a link to the invoice itself: fee-invoices.*
 * routes require fees.view/fees.manage, permissions a parent account
 * never has, so a clickable link here would just 404/403 for the person
 * receiving it. The notification is the same short, essential-facts-only
 * content as the printable slip (fee-invoices/print.blade.php) - student
 * name, fee head/month, amount due, due date - nothing more.
 */
class FeeReminderNotification extends Notification
{
    public function __construct(private readonly FeeInvoice $invoice)
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
        $invoice = $this->invoice;

        return [
            'fee_invoice_id' => $invoice->id,
            'student_name' => $invoice->student->fullName(),
            'fee_head' => $invoice->feeStructure->name,
            'month' => $invoice->due_date
                ? JalaliDate::monthYearDisplay(substr($invoice->due_date, 0, 7))
                : null,
            'amount' => $invoice->balance(),
            'due_date' => $invoice->due_date,
        ];
    }
}
