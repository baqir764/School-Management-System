<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch the UI language (master spec section 37). Persists to the
     * session immediately, and to the user's saved preference when logged
     * in, so the choice survives future logins too.
     */
    public function update(Request $request, string $locale): RedirectResponse
    {
        $available = array_keys(config('app.available_locales'));

        abort_unless(in_array($locale, $available, true), 404);

        $request->session()->put('locale', $locale);

        if ($request->user()) {
            $request->user()->forceFill(['preferred_locale' => $locale])->save();
        }

        return back();
    }
}
