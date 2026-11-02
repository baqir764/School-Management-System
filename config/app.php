<?php

return [

    'name' => env('APP_NAME', 'School Management System'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'timezone' => env('APP_TIMEZONE', 'Asia/Kabul'),

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
     * Locales the School Management System UI supports, in display order.
     * 'dir' controls the <html dir> attribute (RTL for Dari and Pashto).
     */
    'available_locales' => [
        'en' => ['label' => 'English', 'native' => 'English', 'dir' => 'ltr'],
        'fa' => ['label' => 'Dari', 'native' => 'دری', 'dir' => 'rtl'],
        'ps' => ['label' => 'Pashto', 'native' => 'پښتو', 'dir' => 'rtl'],
    ],

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
     * Application-level service providers live in bootstrap/providers.php.
     * Package providers (Spatie Permission, and later phases' PDF/Excel
     * export packages, ...) are picked up automatically by Composer's
     * package:discover during `composer install`.
     */

];
