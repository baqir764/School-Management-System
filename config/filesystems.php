<?php

use Illuminate\Support\Facades\Storage;

return [

    'default' => env('FILESYSTEM_DISK', 'local'),

    'disks' => [

        /*
         * NOTE: 'serve' is deliberately FALSE on every disk here. Laravel 12
         * auto-registers GET /storage/{path} for any local disk with
         * 'serve' => true (see FilesystemServiceProvider::serveFiles), which
         * caused two separate site-wide breakages of all uploaded
         * photos/logos: the private 'local' disk (no 'url', so it defaulted
         * to /storage) hijacked every public URL and 403'd them for missing
         * signatures; and the 'public' disk's auto-route is built from
         * APP_URL, so on subfolder installs (local XAMPP) it registers a
         * wrong URI that never matches. Instead, the explicit storage.local
         * route in routes/web.php serves the public disk in EVERY
         * environment, identically.
         */
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => false,
            'throw' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'serve' => false,
            'throw' => false,
        ],

        /*
         * Private, non-web-exposed disk for sensitive student/staff documents
         * (birth certificates, IDs, transfer certificates). Served only
         * through an authorized controller route - never a public URL.
         */
        'secure_documents' => [
            'driver' => 'local',
            'root' => storage_path('app/secure_documents'),
            'visibility' => 'private',
            'throw' => false,
        ],

        'backups' => [
            'driver' => 'local',
            'root' => storage_path('app/backups'),
            'visibility' => 'private',
            'throw' => false,
        ],

    ],

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
