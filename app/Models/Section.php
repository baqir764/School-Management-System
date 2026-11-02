<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sections don't soft-delete: SectionController::destroy() blocks deletion
 * outright whenever dependent records exist (enrollments, timetable
 * entries, attendance records), so by the time delete() runs it's meant to
 * be a real, permanent removal - see the drop_soft_deletes migration for
 * why a trashed row was actively harmful here (it kept blocking
 * re-registration of the same class/name pair).
 */
class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'class_id',
        'name',
        'class_teacher_id',
        'capacity',
        'room_number',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function classTeacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'class_teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * Students currently enrolled in this section for the given academic
     * year (defaults to whichever year is active). Used by the attendance
     * roster and anywhere else that needs "who's actually in this section
     * right now" rather than the section's whole enrollment history.
     */
    public function activeStudents(?int $academicYearId = null)
    {
        $academicYearId ??= AcademicYear::current()?->id;

        return Student::query()
            ->whereHas('enrollments', function ($query) use ($academicYearId) {
                $query->where('section_id', $this->id)
                    ->where('status', 'active')
                    ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId));
            })
            ->orderBy('first_name');
    }

    public function label(): string
    {
        return trim($this->classRoom?->name.' - '.$this->name);
    }
}
