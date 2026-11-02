<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GradeScale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'grade_name',
        'min_percentage',
        'max_percentage',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'min_percentage' => 'decimal:2',
            'max_percentage' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /**
     * Looks up which grade band a percentage falls into. Queried fresh
     * (not cached) since the scale is small and rarely changes; returns
     * null if no band covers it (e.g. the scale hasn't been configured).
     */
    public static function forPercentage(?float $percentage): ?self
    {
        if (is_null($percentage)) {
            return null;
        }

        return static::query()
            ->where('min_percentage', '<=', $percentage)
            ->where('max_percentage', '>=', $percentage)
            ->orderBy('sort_order')
            ->first();
    }
}
