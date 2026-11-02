<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class AcademicYear extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
        'remarks',
    ];

    /**
     * start_date/end_date are plain Shamsi "YYYY-MM-DD" strings, not cast
     * to 'date' - see Student::$date_of_birth's comment
     * (App\Support\JalaliDate) for why. String comparison (orderBy,
     * "after start_date" checks) stays correct because both are always
     * zero-padded.
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * The single academic year currently marked active, or null if none has
     * been activated yet (e.g. right after a fresh install).
     */
    public static function current(): ?self
    {
        return static::query()->active()->first();
    }

    /**
     * Makes this the one active academic year, deactivating every other
     * one. Wrapped in a transaction so the "exactly one active year"
     * invariant can never be observed broken mid-request.
     */
    public function activate(): void
    {
        DB::transaction(function () {
            static::query()->where('id', '!=', $this->id)->update(['is_active' => false]);
            $this->update(['is_active' => true]);
        });
    }
}
