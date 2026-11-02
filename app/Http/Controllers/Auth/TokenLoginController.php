<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Single-use login bridge for the APK (see Api\AttendanceApiController::
 * session, which mints the nonce): validates the nonce (one-time, 2-minute
 * cache entry), logs that user into the normal cookie session, and lands on
 * the dashboard - so one login inside the app also signs the user into the
 * live site, with no second login screen. Unknown, expired, or
 * already-used nonces get a plain 403.
 *
 * A controller (not a route closure) so `php artisan route:cache` works.
 */
class TokenLoginController extends Controller
{
    public function __invoke(string $nonce): RedirectResponse
    {
        $userId = Cache::pull('sso:'.$nonce);
        $user = $userId ? User::find($userId) : null;

        abort_unless($user && $user->isActive(), 403);

        // Explicit web guard: the token-authenticated API context this nonce
        // came from must never leak into which session guard signs in here.
        Auth::guard('web')->login($user);

        return redirect('/dashboard');
    }
}
