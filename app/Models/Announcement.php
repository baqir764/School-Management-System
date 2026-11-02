<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Announcement extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'body',
        'target_roles',
        'target_user_ids',
        'created_by',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'target_roles' => 'array',
            'target_user_ids' => 'array',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function isVisibleTo(User $user): bool
    {
        // Managers see everything (they author it).
        if ($user->hasAnyRole(['Super Administrator', 'School Administrator'])) {
            return true;
        }

        // Authors always see their own message (e.g. a parent re-reading
        // what they sent to a teacher).
        if ($this->created_by && (int) $this->created_by === (int) $user->id) {
            return true;
        }

        // Unpublished is draft - only managers above (and the author, just
        // handled) can see it.
        if (! $this->is_published) {
            return false;
        }

        // Parent-to-teacher message: addressed to specific user accounts.
        $targetUserIds = array_map('intval', (array) ($this->target_user_ids ?? []));

        if (! empty($targetUserIds)) {
            return in_array((int) $user->id, $targetUserIds, true);
        }

        $targets = $this->target_roles;

        // null/empty = broadcast to everyone.
        if (empty($targets)) {
            return true;
        }

        return $user->hasAnyRole($targets);
    }

    public function audienceLabel(): string
    {
        $targetUserIds = array_map('intval', (array) ($this->target_user_ids ?? []));

        if (! empty($targetUserIds)) {
            $names = User::query()->whereIn('id', $targetUserIds)->pluck('name')->all();

            return $names === [] ? __('app.announcement_audience_teachers') : implode(', ', $names);
        }

        if (empty($this->target_roles)) {
            return __('app.announcement_audience_all');
        }

        return implode(', ', $this->target_roles);
    }
}
