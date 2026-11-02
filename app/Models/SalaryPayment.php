<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SalaryPayment extends Model
{
    use HasFactory, SoftDeletes;

    public const METHODS = ['cash', 'bank_transfer', 'other'];

    protected $fillable = [
        'payee_type',
        'payee_id',
        'amount',
        'discount_amount',
        'salary_month',
        'payment_date',
        'payment_method',
        'voucher_number',
        'paid_by',
        'remarks',
    ];

    /**
     * salary_month is a month-only Jalali "YYYY-MM" string and
     * payment_date a Jalali "YYYY-MM-DD" string (see App\Support\JalaliDate)
     * - neither is a Carbon-cast column, since a 'date' cast would make
     * Eloquent try to parse the Jalali string as Gregorian and corrupt it.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    /**
     * The amount actually handed to the payee after subtracting
     * discount_amount from the base amount - never negative even if a
     * discount larger than the base amount was somehow saved.
     */
    public function netAmount(): float
    {
        return max(0.0, round((float) $this->amount - (float) $this->discount_amount, 2));
    }

    /**
     * Either a Teacher or an Employee - see the morph map registered in
     * AppServiceProvider (stores 'teacher'/'employee', not full class
     * names).
     */
    public function payee(): MorphTo
    {
        return $this->morphTo();
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    /**
     * Next sequential payroll voucher number, e.g. "SAL-1405-0007"
     * (numbered by Shamsi year, like FeePayment::nextReceiptNumber()).
     * Same per-year-highest-suffix approach, kept in its own "SAL-" series
     * so payroll vouchers and fee receipts never collide.
     */
    public static function nextVoucherNumber(): string
    {
        $year = \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
        $prefix = "SAL-{$year}-";

        $last = static::withTrashed()
            ->where('voucher_number', 'like', $prefix.'%')
            ->orderByDesc('id')
            ->value('voucher_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
