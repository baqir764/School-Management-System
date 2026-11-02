<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    public const EMPLOYMENT_TYPES = ['full_time', 'part_time', 'contract'];

    public const STATUSES = ['active', 'inactive', 'terminated'];

    protected $fillable = [
        'user_id',
        'employee_code',
        'first_name',
        'last_name',
        'father_name',
        'gender',
        'date_of_birth',
        'national_id',
        'phone',
        'email',
        'address',
        'qualification',
        'specialization',
        'joining_date',
        'employment_type',
        'shift_id',
        'monthly_salary',
        'status',
        'photo_path',
    ];

    /**
     * date_of_birth and joining_date are plain Shamsi "YYYY-MM-DD"
     * strings, not cast to 'date' - see Student's own date_of_birth
     * comment (App\Support\JalaliDate) for why.
     */
    protected function casts(): array
    {
        return [
            'monthly_salary' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function homeroomSections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_teacher_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'teacher_id');
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function salaryPayments(): MorphMany
    {
        return $this->morphMany(SalaryPayment::class, 'payee');
    }

    /**
     * This teacher's own daily session (دوره), assigned directly since a
     * teacher isn't tied to a single class. Null falls back to
     * Setting::school_start_time - see AttendanceController::expectedStartTime().
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
     * Next sequential employee code, e.g. "TCH-0007". Uses the highest
     * existing numeric suffix (including soft-deleted rows) rather than a
     * row count, so codes stay unique even after deletions.
     */
    public static function nextEmployeeCode(): string
    {
        $last = static::withTrashed()
            ->where('employee_code', 'like', 'TCH-%')
            ->orderByDesc('id')
            ->value('employee_code');

        $next = $last ? ((int) substr($last, 4)) + 1 : 1;

        return 'TCH-'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
