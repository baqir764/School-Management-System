<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingRequest;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class SchoolSettingController extends Controller
{
    public function edit(): View
    {
        abort_unless(request()->user()->can('settings.manage'), 403);

        return view('settings.edit', ['setting' => Setting::current()]);
    }

    public function update(UpdateSettingRequest $request): RedirectResponse
    {
        $setting = Setting::current();

        // Built from the form's field names (e.g. "logo", the uploaded
        // file), which don't map 1:1 to the model's actual columns (the
        // stored path is "logo_path") - so the before/after snapshot below
        // is taken from $data's keys, not the raw validated request.
        $data = $request->safe()->except('logo');

        if ($request->hasFile('logo')) {
            if ($setting->logo_path) {
                Storage::disk('public')->delete($setting->logo_path);
            }
            $data['logo_path'] = $request->file('logo')->store('school-logo', 'public');
        }

        $original = $setting->only(array_keys($data));

        $setting->update($data);

        AuditLogger::log('update', 'settings', $setting->id, $original, $setting->only(array_keys($data)));

        return back()->with('status', __('app.settings_updated'));
    }
}
