<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'terminated'];

    protected $fillable = [
        'employee_code',
        'first_name',
        'last_name',
        'position',
        'shift_id',
        'phone',
        'national_id',
        'monthly_salary',
        'joining_date',
        'status',
    ];

    /**
     * joining_date is stored as a Jalali "YYYY-MM-DD" string (see
     * App\Support\JalaliDate), not a Carbon-cast column - a 'date' cast
     * would make Eloquent try to parse the Jalali string as Gregorian and
     * corrupt it.
     */
    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function salaryPayments(): MorphMany
    {
        return $this->morphMany(SalaryPayment::class, 'payee');
    }

    /**
     * This employee's own daily session (دوره), assigned directly since
     * non-teaching staff aren't tied to any class at all. Null falls back
     * to Setting::school_start_time - see AttendanceController::expectedStartTime().
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    /**
     * Next sequential employee code, e.g. "STF-0001". Distinct prefix from
     * Teacher::nextEmployeeCode()'s "TCH-" since these are separate tables
     * with no shared uniqueness constraint between them.
     */
    public static function nextEmployeeCode(): string
    {
        $last = static::withTrashed()
            ->where('employee_code', 'like', 'STF-%')
            ->orderByDesc('id')
            ->value('employee_code');

        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'STF-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
