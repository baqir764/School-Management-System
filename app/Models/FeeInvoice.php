<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeInvoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'fee_structure_id',
        'academic_year_id',
        'amount',
        'discount_amount',
        'due_date',
    ];

    /**
     * due_date is stored as a Jalali "YYYY-MM-DD" string (see
     * App\Support\JalaliDate), not a Carbon-cast column - a 'date' cast
     * would make Eloquent try to parse the Jalali string as Gregorian and
     * corrupt it.
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(FeePayment::class);
    }

    /**
     * Sums the already-loaded payments relation - callers must eager
     * load 'payments' first (this app runs with strict Eloquent mode, so
     * an unloaded relation throws rather than silently lazy-loading).
     */
    public function totalPaid(): float
    {
        return (float) $this->payments->sum('amount');
    }

    /**
     * What's actually owed after discount - never negative even if a
     * discount_amount larger than amount was somehow saved. amount itself
     * stays an untouched snapshot of the fee structure at generation time
     * (see the fee_invoices migration); this is the figure balance()/
     * status() compare against payments, not amount directly.
     */
    public function payableAmount(): float
    {
        return max(0.0, round((float) $this->amount - (float) $this->discount_amount, 2));
    }

    public function balance(): float
    {
        return round($this->payableAmount() - $this->totalPaid(), 2);
    }

    /**
     * A zero-or-negative balance means "paid" even when nothing was ever
     * collected - a full discount can bring the payable amount itself
     * down to zero, and that student owes nothing further either way.
     */
    public function status(): string
    {
        if ($this->balance() <= 0.0) {
            return 'paid';
        }

        return $this->totalPaid() > 0 ? 'partial' : 'unpaid';
    }
}
