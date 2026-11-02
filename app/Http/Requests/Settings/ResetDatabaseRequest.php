<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the "Reset Database" danger-zone action (BackupController::
 * resetDatabase()). Two independent confirmations are required, per how
 * this was scoped: the exact word "RESET" (guards against a stray click -
 * unlike a JS confirm() dialog, it can't be dismissed by muscle-memory)
 * and the account's own current password (guards against a hijacked/left-
 * open admin session). settings.restore is Super Administrator-only - see
 * RoleAndPermissionSeeder.
 */
class ResetDatabaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.restore');
    }

    public function rules(): array
    {
        return [
            'confirmation_phrase' => ['required', 'string', Rule::in(['RESET'])],
            'current_password' => ['required', 'string', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmation_phrase.in' => __('app.error_confirmation_phrase_mismatch'),
        ];
    }
}
