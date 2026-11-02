<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A daily session (دوره) - e.g. "Morning" starting 07:00, "Afternoon"
 * starting 13:00. A school running more than one session assigns each
 * Class to a shift (every section of that class inherits it) and, since
 * staff don't belong to a single class, assigns Teachers and Employees to
 * a shift directly. Attendance "late" detection then judges each person
 * against their own shift's start time instead of one school-wide clock;
 * Setting::school_start_time stays as the fallback for anyone/anything
 * with no shift assigned - see AttendanceController::expectedStartTime().
 */
class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassRoom::class);
    }

    public function teachers(): HasMany
    {
        return $this->hasMany(Teacher::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('start_time');
    }

    /**
     * "07:00" - start_time comes back from MySQL as a "HH:MM:SS" string (no
     * Eloquent cast applied), so this is just string slicing, matching
     * Period::timeRangeLabel()'s own convention.
     */
    public function startTimeLabel(): string
    {
        return substr($this->start_time, 0, 5);
    }
}
