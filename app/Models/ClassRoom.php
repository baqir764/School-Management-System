<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Represents a grade/class level (e.g. "Grade 1"). Named ClassRoom instead
 * of Class because "Class" is a reserved word in PHP.
 *
 * Classes don't soft-delete: ClassRoomController::destroy() blocks deletion
 * outright whenever dependent records exist (sections, enrollments, fee
 * structures), so by the time delete() runs it's meant to be a real,
 * permanent removal - see the drop_soft_deletes migration for why a
 * trashed row was actively harmful here (it kept blocking re-registration
 * of the same numeric_level).
 */
class ClassRoom extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'name',
        'numeric_level',
        'shift_id',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'numeric_level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'class_id');
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class, 'class_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'class_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'class_id');
    }

    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class, 'class_id');
    }

    /**
     * The daily session (دوره) this class runs in - e.g. "Morning" vs
     * "Afternoon" - used to judge its students' attendance "late" status
     * against the right start time. Null means "no specific shift", which
     * falls back to Setting::school_start_time everywhere shifts are read.
     */
    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('numeric_level');
    }
}
