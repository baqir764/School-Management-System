<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep compatibility with older MySQL/MariaDB (utf8mb4 index length).
        Schema::defaultStringLength(191);

        // Bootstrap-styled pagination views (Bootstrap 5).
        Paginator::useBootstrapFive();

        // Master spec section 34: strong password hashing / validation.
        Password::defaults(fn () => Password::min(10)->mixedCase()->numbers()->symbols());

        // Fail loudly in development on silent mass-assignment drops, lazy
        // loading, and access to missing attributes - all easy to miss
        // otherwise on a project this size.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Payroll's SalaryPayment::payee() is polymorphic (Teacher or
        // Employee). A short morph map keeps 'teacher'/'employee' in the
        // payee_type column and in URLs/query strings, instead of leaking
        // full "App\Models\Teacher" class names into the database and
        // routes. This MUST be the non-enforcing morphMap(), not
        // enforceMorphMap(): the strict variant throws for every
        // polymorphic relation touching a model outside this map - and
        // spatie/laravel-permission's own model_has_roles/
        // model_has_permissions pivots are polymorphic on User, which is
        // deliberately not (and must never be) in this map. morphMap()
        // only registers the two aliases below and leaves every other
        // model - User included - free to keep using its own class name.
        Relation::morphMap([
            'teacher' => \App\Models\Teacher::class,
            'employee' => \App\Models\Employee::class,
        ]);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        /*
         * This app is commonly deployed straight under htdocs, in a
         * subdirectory (e.g. http://localhost/School-Management-System/public)
         * rather than at the web server's document root. Without this,
         * Laravel derives the "root" for generated URLs (redirect()->to(),
         * Route::redirect(), the auth middleware's login/dashboard
         * redirects, asset URLs, ...) from the incoming request, which can
         * drop the subdirectory and send the browser to the bare domain
         * root instead - on XAMPP that's especially confusing, since
         * http://localhost/dashboard collides with XAMPP's own bundled
         * dashboard page. Forcing the root from APP_URL keeps every
         * generated URL consistent with wherever the app actually lives.
         */
        if ($appUrl = config('app.url')) {
            URL::forceRootUrl($appUrl);
        }
    }
}
