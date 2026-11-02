<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.manage');
    }

    public function rules(): array
    {
        return [
            'school_name' => ['required', 'string', 'max:190'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:190'],
            'website' => ['nullable', 'url', 'max:190'],
            'principal_name' => ['nullable', 'string', 'max:190'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'size:3'],
            'timezone' => ['required', 'string', 'max:64', 'timezone'],
            'school_start_time' => ['nullable', 'date_format:H:i'],
            'default_locale' => ['required', 'string', 'in:'.implode(',', array_keys(config('app.available_locales')))],
        ];
    }
}
