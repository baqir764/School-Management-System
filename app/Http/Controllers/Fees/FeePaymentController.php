<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\StoreFeePaymentRequest;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Models\User;
use App\Notifications\FeeReminderNotification;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class FeePaymentController extends Controller
{
    public function store(StoreFeePaymentRequest $request, FeeInvoice $fee_invoice): RedirectResponse
    {
        $payment = FeePayment::create([
            'fee_invoice_id' => $fee_invoice->id,
            'amount' => $request->validated('amount'),
            'payment_date' => $request->jalaliDateFromInput('payment_date'),
            'payment_method' => $request->validated('payment_method'),
            'receipt_number' => FeePayment::nextReceiptNumber(),
            'received_by' => $request->user()->id,
            'remarks' => $request->validated('remarks'),
        ]);

        AuditLogger::log('create', 'fee_payments', $payment->id, null, $payment->only([
            'fee_invoice_id', 'amount', 'payment_method', 'receipt_number',
        ]));

        // Once the invoice is fully paid, its reminders have served their
        // purpose - clear every still-unread one so parents' inboxes stop
        // showing a debt that no longer exists. A partial payment leaves
        // them unread (the debt is still live).
        $fee_invoice->load('payments');

        if ($fee_invoice->status() === 'paid') {
            self::resolveFeeReminders($fee_invoice);
        }

        return redirect()->route('fee-payments.receipt', $payment)->with('status', __('app.fee_payment_recorded'));
    }

    public function receipt(FeePayment $fee_payment): View
    {
        $fee_payment->load(['invoice.student', 'invoice.feeStructure', 'invoice.payments', 'receivedBy']);

        return view('fee-payments.receipt', ['payment' => $fee_payment]);
    }

    /**
     * Marks every still-unread fee-reminder notification for a now fully
     * paid invoice as read. Scoped strictly to FeeReminderNotification
     * rows for this invoice (matched in PHP on the data payload, so no
     * JSON-column syntax is assumed) - announcements and every other
     * notification type are never touched.
     */
    private static function resolveFeeReminders(FeeInvoice $invoice): void
    {
        DatabaseNotification::query()
            ->whereNull('read_at')
            ->where('type', FeeReminderNotification::class)
            ->where('notifiable_type', (new User)->getMorphClass())
            ->get()
            ->filter(fn (DatabaseNotification $n) => ($n->data['fee_invoice_id'] ?? null) == $invoice->id)
            ->each(fn (DatabaseNotification $n) => $n->markAsRead());
    }
}
