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
     * Container key under which the per-request memo of the settings row
     * is stored (see current()).
     */
    private const MEMO_KEY = 'school.settings.memo';

    /**
     * The one settings row, cached forever AND memoized for the rest of the
     * current request/application instance. The layout, sidebar and
     * dashboard all call current() several times per page; without the
     * memo each call re-read and re-unserialized the cache entry (a file
     * read on the default file cache store).
     *
     * The memo lives in the container (not a static property) on purpose:
     * a static would leak across tests in one PHPUnit process and across
     * requests under long-running runtimes, while a container instance is
     * scoped to exactly one application instance - one request in classic
     * PHP-FPM/mod_php, one test in the test suite. The memo is dropped
     * together with the cache entry whenever the row is saved/deleted, so
     * a settings update is visible immediately in the same request.
     */
    public static function current(): self
    {
        $app = app();

        if ($app->bound(self::MEMO_KEY)) {
            return $app->make(self::MEMO_KEY);
        }

        $setting = Cache::rememberForever('school.settings', function () {
            return static::query()->first() ?? static::create([
                'school_name' => 'School Management System',
            ]);
        });

        $app->instance(self::MEMO_KEY, $setting);

        return $setting;
    }

    protected static function booted(): void
    {
        static::saved(function () {
            app()->forgetInstance(self::MEMO_KEY);
            Cache::forget('school.settings');
        });
        static::deleted(function () {
            app()->forgetInstance(self::MEMO_KEY);
            Cache::forget('school.settings');
        });
    }
}
