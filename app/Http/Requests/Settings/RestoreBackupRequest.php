<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the "Restore Backup" danger-zone action (BackupController::
 * restoreBackup()) - see ResetDatabaseRequest for why both a typed
 * confirmation word and the account's current password are required.
 * settings.restore is Super Administrator-only.
 */
class RestoreBackupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.restore');
    }

    public function rules(): array
    {
        return [
            'backup_file' => ['required', 'file', 'mimes:zip', 'max:512000'],
            'confirmation_phrase' => ['required', 'string', Rule::in(['RESTORE'])],
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
