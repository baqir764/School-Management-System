<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeeStructure extends Model
{
    use HasFactory, SoftDeletes;

    public const FREQUENCIES = ['one_time', 'monthly', 'term', 'annual'];

    protected $fillable = [
        'academic_year_id',
        'class_id',
        'name',
        'amount',
        'frequency',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
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

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * A null class_id means this fee head applies school-wide - every
     * class for that academic year - rather than one specific class.
     */
    public function appliesToAllClasses(): bool
    {
        return is_null($this->class_id);
    }
}
