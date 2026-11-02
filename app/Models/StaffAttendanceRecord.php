<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StaffAttendanceRecord extends Model
{
    protected $fillable = [
        'attendee_type',
        'attendee_id',
        'date',
        'status',
        'time_in',
        'time_out',
        'marked_by',
    ];

    /**
     * date is a plain Shamsi "YYYY-MM-DD" string, not cast to 'date' - see
     * Student::$date_of_birth's comment (App\Support\JalaliDate).
     */
    public function attendee(): MorphTo
    {
        return $this->morphTo();
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * Exact string match, not whereDate() - 'date' is a plain Jalali
     * string (see AttendanceRecord::scopeOnDate for why).
     */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->where('date', $date);
    }
}
