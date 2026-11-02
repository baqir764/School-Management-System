<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'school_name',
        'logo_path',
        'address',
        'province',
        'district',
        'phone',
        'email',
        'website',
        'principal_name',
        'registration_number',
        'currency',
        'timezone',
        'school_start_time',
        'default_locale',
    ];

    /**
     * There is always exactly one settings row. Fetch it (cached), creating
     * the default row on first boot if it doesn't exist yet.
     */
    public static function current(): self
    {
        return Cache::rememberForever('school.settings', function () {
            return static::query()->first() ?? static::create([
                'school_name' => 'School Management System',
            ]);
        });
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('school.settings'));
        static::deleted(fn () => Cache::forget('school.settings'));
    }
}
