<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeePayment extends Model
{
    use HasFactory, SoftDeletes;

    public const METHODS = ['cash', 'bank_transfer', 'other'];

    protected $fillable = [
        'fee_invoice_id',
        'amount',
        'payment_date',
        'payment_method',
        'receipt_number',
        'received_by',
        'remarks',
    ];

    /**
     * payment_date is stored as a Jalali "YYYY-MM-DD" string (see
     * App\Support\JalaliDate), not a Carbon-cast column - a 'date' cast
     * would make Eloquent try to parse the Jalali string as Gregorian and
     * corrupt it.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(FeeInvoice::class, 'fee_invoice_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Next sequential receipt number, e.g. "RCPT-1405-0007" (numbered by
     * Shamsi year, like Student::nextAdmissionNumber()). Uses the highest
     * existing numeric suffix for the current year (including
     * soft-deleted rows) rather than a row count, so numbers stay unique
     * even after a receipt is voided/deleted - the same pattern as
     * Student::nextAdmissionNumber() and Teacher::nextEmployeeCode().
     */
    public static function nextReceiptNumber(): string
    {
        $year = \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
        $prefix = "RCPT-{$year}-";

        $last = static::withTrashed()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
