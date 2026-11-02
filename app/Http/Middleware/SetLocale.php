<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active UI language (master spec section 37: English, Dari,
 * Pashto with RTL support) from, in order: an explicit session choice, the
 * authenticated user's saved preference, then the school's default locale.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $available = array_keys(config('app.available_locales'));

        $locale = $request->session()->get('locale')
            ?? $request->user()?->preferred_locale
            ?? config('app.locale');

        if (! in_array($locale, $available, true)) {
            $locale = config('app.fallback_locale');
        }

        App::setLocale($locale);

        return $next($request);
    }
}
