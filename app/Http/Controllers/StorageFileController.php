<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves uploaded files (photos/logos/documents) from storage/app/public
 * through an explicit route, so they keep working with NO
 * "public/storage" symlink at all - which shared hosts without shell
 * access (like this project's InfinityFree account, where
 * `php artisan storage:link` can never run) require. The resolved path
 * is checked to still be inside that folder before anything is served,
 * so no traversal is possible.
 *
 * Do NOT "fix" this by enabling 'serve' => true on a filesystem disk
 * instead: Laravel 12's auto-registered storage routes broke all uploads
 * site-wide twice (403s from the private disk hijacking /storage, then a
 * wrong subfolder-prefixed URI). This explicit route behaves identically
 * in every environment.
 *
 * Lives in a controller (not a route closure) so `php artisan route:cache`
 * works - closures cannot be serialized into the cached routes file.
 */
class StorageFileController extends Controller
{
    public function __invoke(string $path): BinaryFileResponse
    {
        $root = realpath(storage_path('app/public'));
        $fullPath = $root ? realpath($root.'/'.$path) : false;

        abort_unless($fullPath && str_starts_with($fullPath, $root) && is_file($fullPath), 404);

        return response()->file($fullPath);
    }
}
