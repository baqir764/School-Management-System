<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Expense extends Model
{
    use HasFactory, SoftDeletes;

    public const METHODS = ['cash', 'bank_transfer', 'other'];

    protected $fillable = [
        'expense_category_id',
        'title',
        'amount',
        'expense_date',
        'payment_method',
        'voucher_number',
        'paid_by',
        'remarks',
    ];

    /**
     * expense_date is a Jalali "YYYY-MM-DD" string (see App\Support\JalaliDate),
     * not a Carbon-cast column - same convention as salary_payments.payment_date.
     */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Next sequential expense voucher number, e.g. "EXP-1405-0007" - same
     * per-year-highest-suffix approach as SalaryPayment::nextVoucherNumber()
     * and FeePayment::nextReceiptNumber(), kept in its own "EXP-" series so
     * it never collides with payroll vouchers or fee receipts.
     */
    public static function nextVoucherNumber(): string
    {
        $year = \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
        $prefix = "EXP-{$year}-";

        $last = static::withTrashed()
            ->where('voucher_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('voucher_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
