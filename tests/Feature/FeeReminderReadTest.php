<?php

namespace Tests\Feature;

use App\Models\FeeInvoice;
use App\Models\User;
use App\Notifications\FeeReminderNotification;
use App\Support\JalaliDate;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeeReminderReadTest extends TestCase
{
    use RefreshDatabase;

    protected User $accountant;

    protected User $parent;

    protected array $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        $this->parent = User::factory()->create();
        $this->parent->assignRole('Parent');

        [$year, $month, $day] = JalaliDate::parts(JalaliDate::today());
        $this->today = ['year' => $year, 'month' => $month, 'day' => $day];
    }

    private function remindParent(FeeInvoice $invoice): void
    {
        // FeeReminderNotification reads student/feeStructure/payments off
        // the model, and the app runs in strict Eloquent mode, so the
        // relations must be loaded before notifying.
        $invoice->loadMissing(['student', 'feeStructure', 'payments']);
        $this->parent->notify(new FeeReminderNotification($invoice));
    }

    public function test_parent_cannot_mark_fee_reminder_read_while_invoice_is_unpaid(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 1000]);
        $this->remindParent($invoice);
        $notification = $this->parent->notifications()->firstOrFail();

        $response = $this->actingAs($this->parent)->post(route('notifications.read', $notification->id));

        $response->assertSessionHas('error');
        $this->assertNull($notification->refresh()->read_at);
    }

    public function test_partial_payment_keeps_the_fee_reminder_unread(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 1000]);
        $this->remindParent($invoice);

        $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 400, 'payment_date' => $this->today, 'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertNull($this->parent->notifications()->firstOrFail()->read_at);
    }

    public function test_full_payment_auto_marks_the_fee_reminder_read(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);
        $this->remindParent($invoice);

        $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 500, 'payment_date' => $this->today, 'payment_method' => 'cash',
        ])->assertRedirect();

        $this->assertNotNull($this->parent->notifications()->firstOrFail()->refresh()->read_at);
    }

    public function test_read_all_skips_unpaid_fee_reminder_with_an_error(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);
        $this->remindParent($invoice);

        $response = $this->actingAs($this->parent)->post(route('notifications.read-all'));

        $response->assertSessionHas('error');
        $this->assertNull($this->parent->notifications()->firstOrFail()->refresh()->read_at);
    }

    public function test_read_all_marks_fee_reminder_once_the_invoice_is_paid(): void
    {
        $invoice = FeeInvoice::factory()->create(['amount' => 500]);
        $this->remindParent($invoice);

        $this->actingAs($this->accountant)->post(route('fee-invoices.payments.store', $invoice), [
            'amount' => 500, 'payment_date' => $this->today, 'payment_method' => 'cash',
        ])->assertRedirect();

        // Already auto-resolved by the payment; read-all must not error.
        $response = $this->actingAs($this->parent)->post(route('notifications.read-all'));

        $response->assertSessionMissing('error');
        $this->assertNotNull($this->parent->notifications()->firstOrFail()->refresh()->read_at);
    }
}
