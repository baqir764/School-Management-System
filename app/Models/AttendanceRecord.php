<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttendanceRecord extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['present', 'absent', 'late', 'excused'];

    protected $fillable = [
        'student_id',
        'section_id',
        'academic_year_id',
        'date',
        'status',
        'time_in',
        'time_out',
        'remarks',
        'marked_by',
    ];

    /**
     * date is a plain Shamsi "YYYY-MM-DD" string, not cast to 'date' -
     * see Student::$date_of_birth's comment (App\Support\JalaliDate).
     */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Exact string match, not whereDate() - 'date' is a plain Jalali
     * string, and whereDate() would wrap it in MySQL's DATE() function,
     * which validates against the GREGORIAN calendar and would silently
     * return NULL for a perfectly valid Jalali date whose day/month
     * combination isn't valid in the Gregorian calendar (e.g. the 31st
     * of a Jalali month that would be a 30-day Gregorian month).
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->where('date', $date);
    }
}
