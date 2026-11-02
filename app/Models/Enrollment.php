<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes;

    public const STATUSES = ['active', 'promoted', 'repeated', 'transferred_out', 'withdrawn'];

    protected $fillable = [
        'student_id',
        'academic_year_id',
        'class_id',
        'section_id',
        'roll_number',
        'enrollment_date',
        'status',
        'remarks',
    ];

    /**
     * enrollment_date is a plain Shamsi "YYYY-MM-DD" string, not cast to
     * 'date' - see Student::$date_of_birth's comment
     * (App\Support\JalaliDate) for why.
     */

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
