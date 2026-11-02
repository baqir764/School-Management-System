<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Period extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('start_time');
    }

    /**
     * Formatted "08:00 - 08:45" label. start_time/end_time come back from
     * MySQL as "HH:MM:SS" strings (no Eloquent cast applied), so this is
     * just string slicing rather than a Carbon round-trip.
     */
    public function timeRangeLabel(): string
    {
        return substr($this->start_time, 0, 5).' - '.substr($this->end_time, 0, 5);
    }
}
