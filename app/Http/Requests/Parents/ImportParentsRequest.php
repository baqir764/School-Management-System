<?php

namespace App\Http\Requests\Parents;

use Illuminate\Foundation\Http\FormRequest;

class ImportParentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('parents.manage');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ];
    }
}
