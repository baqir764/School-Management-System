<?php

namespace App\Services;

use App\Models\Section;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Which sections a user may mark/view attendance for. A Teacher is limited
 * to their own homeroom section(s); privileged roles (administrators,
 * Principal, Attendance Clerk) see every section. Shared by the web
 * attendance screens and the APK sync API so both enforce the same scope.
 */
final class AccessibleSections
{
    public static function for(User $user): Collection
    {
        $query = Section::query()
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->select('sections.*')
            ->with('classRoom.shift')
            ->orderBy('classes.numeric_level')
            ->orderBy('sections.name');

        if ($user->hasAnyRole(['Super Administrator', 'School Administrator', 'Principal', 'Attendance Clerk'])) {
            return $query->get();
        }

        $homeroomIds = $user->teacher?->homeroomSections()->pluck('id') ?? collect();

        return $query->whereIn('sections.id', $homeroomIds)->get();
    }
}
