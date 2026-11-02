<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MiscIncome extends Model
{
    use HasFactory, SoftDeletes;

    public const METHODS = ['cash', 'bank_transfer', 'other'];

    protected $fillable = [
        'income_category_id',
        'title',
        'amount',
        'received_date',
        'payment_method',
        'receipt_number',
        'received_by',
        'remarks',
    ];

    /**
     * received_date is a Jalali "YYYY-MM-DD" string (see App\Support\JalaliDate),
     * not a Carbon-cast column - same convention as fee_payments.payment_date.
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(IncomeCategory::class, 'income_category_id');
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * Next sequential receipt number, e.g. "MISC-1405-0007" - same
     * per-year-highest-suffix approach as FeePayment::nextReceiptNumber(),
     * kept in its own "MISC-" series so it never collides with fee receipts.
     */
    public static function nextReceiptNumber(): string
    {
        $year = \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
        $prefix = "MISC-{$year}-";

        $last = static::withTrashed()
            ->where('receipt_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('receipt_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
