<?php

use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SetLocale::class,
        ]);

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'force_password_change' => ForcePasswordChange::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/dashboard');
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Some shared hosts (notably this project's InfinityFree account) silently
// delete any file literally named ".env" as a blanket security measure,
// since it's the most commonly-scanned-for leaked-secrets filename on the
// web. That breaks Laravel on every request, since it can never find its
// environment file no matter how many times it's re-uploaded.
//
// Workaround: if a file named "config.dat" exists next to .env, load the
// environment from THAT file instead - same KEY=VALUE format, just a
// filename that doesn't match the host's ".env" deletion pattern. Local
// development (XAMPP) has no config.dat, so it keeps using .env as normal;
// only the production server (which has config.dat but keeps losing .env)
// is affected.
if (file_exists($app->basePath('config.dat'))) {
    $app->loadEnvironmentFrom('config.dat');
}

// On InfinityFree the project is split in two: everything lives one level
// above the web root, while the web root itself (the old local public/
// folder) is uploaded as htdocs/ and there is NO folder literally named
// "public" next to bootstrap/. Laravel derives public_path() from the base
// path, so without this it looks for the Vite manifest (and anything else
// under public/) at <base>/public/... which doesn't exist there - every
// page then dies with "Vite manifest not found". Pointing the public path
// at the htdocs/ folder fixes it. Local development (XAMPP) has no htdocs/
// folder at the project root, so this is a no-op there.
if (is_dir($app->basePath('htdocs'))) {
    $app->usePublicPath($app->basePath('htdocs'));
}

return $app;
