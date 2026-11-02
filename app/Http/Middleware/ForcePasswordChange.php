<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Users created by an administrator, or whose password was reset by one
 * (master spec section 30), must set their own password before touching
 * anything else in the system.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->must_change_password && ! $request->routeIs('account.password.*', 'logout')) {
            return redirect()->route('account.password.edit');
        }

        return $next($request);
    }
}
