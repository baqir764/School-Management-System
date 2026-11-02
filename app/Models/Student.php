<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Student extends Model
{
    use HasFactory, SoftDeletes;

    public const GENDERS = ['male', 'female'];

    public const STATUSES = ['active', 'inactive', 'graduated', 'transferred', 'expelled'];

    protected $fillable = [
        'user_id',
        'admission_number',
        'first_name',
        'last_name',
        'father_name',
        'grandfather_name',
        'gender',
        'date_of_birth',
        'place_of_birth',
        'national_id',
        'nationality',
        'religion',
        'blood_group',
        'address',
        'province',
        'district',
        'phone',
        'email',
        'photo_path',
        'admission_date',
        'status',
        'previous_school',
        'medical_notes',
    ];

    /**
     * date_of_birth and admission_date are deliberately NOT cast to
     * 'date' - they're stored as plain Shamsi (Jalali/Afghan Solar
     * Hijri) "YYYY-MM-DD" strings (see App\Support\JalaliDate), and a
     * 'date' cast would make Eloquent try to parse a Jalali string as a
     * Gregorian Carbon instance, which corrupts it.
     */

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(StudentDocument::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(Mark::class);
    }

    public function feeInvoices(): HasMany
    {
        return $this->hasMany(FeeInvoice::class);
    }

    public function parents(): BelongsToMany
    {
        return $this->belongsToMany(ParentGuardian::class, 'parent_student', 'student_id', 'parent_id')
            ->withPivot(['relationship', 'is_primary_contact'])
            ->withTimestamps();
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
     * This student's enrollment for the currently active academic year, if
     * any. Used across dashboards/report cards to know "which class is this
     * student in right now" without assuming the latest enrollment row is
     * necessarily the current year's.
     */
    public function currentEnrollment(): ?Enrollment
    {
        $currentYear = AcademicYear::current();

        if (! $currentYear) {
            return null;
        }

        return $this->enrollments()->where('academic_year_id', $currentYear->id)->first();
    }

    /**
     * The Shamsi year embedded in newly generated admission numbers.
     *
     * This is the year of the currently active academic year's start_date
     * (e.g. a year starting 1405-01-01 yields 1405) - NOT today's calendar
     * year - so admission numbers always belong to the academic cycle the
     * student is actually registered under. Falls back to today's Shamsi
     * year when no academic year has been activated yet (fresh install).
     */
    public static function admissionNumberYear(): int
    {
        $currentYear = AcademicYear::current();

        if ($currentYear && $currentYear->start_date) {
            return \App\Support\JalaliDate::parts($currentYear->start_date)[0];
        }

        return \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
    }

    /**
     * Next sequential admission number for the given Shamsi year, e.g.
     * "STU-1405-0001". Scoped per year (including soft-deleted rows) so
     * numbering restarts cleanly each year while staying unique overall.
     * With no explicit year, the active academic year's start-date year is
     * used (see admissionNumberYear()).
     */
    public static function nextAdmissionNumber(?int $year = null): string
    {
        $year ??= static::admissionNumberYear();
        $prefix = "STU-{$year}-";

        $last = static::withTrashed()
            ->where('admission_number', 'like', $prefix.'%')
            ->orderByDesc('admission_number')
            ->value('admission_number');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }
}
