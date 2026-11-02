<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Exam extends Model
{
    use HasFactory, SoftDeletes;

    public const TERM_TYPES = ['quiz', 'mid_term', 'final', 'other'];

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'name',
        'term_type',
        'start_date',
        'end_date',
        'is_published',
        'remarks',
    ];

    /**
     * start_date/end_date are plain Shamsi "YYYY-MM-DD" strings, not cast
     * to 'date' - see Student::$date_of_birth's comment
     * (App\Support\JalaliDate).
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function examSubjects(): HasMany
    {
        return $this->hasMany(ExamSubject::class);
    }

    /**
     * Students actively enrolled in this exam's class for this exam's
     * academic year - the roster marks are entered/reported against.
     * Each student's matching enrollment (for roll number) is eager
     * loaded too, filtered down to just this class/year.
     */
    public function enrolledStudents()
    {
        return Student::query()
            ->whereHas('enrollments', fn ($q) => $q
                ->where('class_id', $this->class_id)
                ->where('academic_year_id', $this->academic_year_id)
                ->where('status', 'active'))
            ->with(['enrollments' => fn ($q) => $q
                ->where('class_id', $this->class_id)
                ->where('academic_year_id', $this->academic_year_id)])
            ->orderBy('first_name');
    }
}
