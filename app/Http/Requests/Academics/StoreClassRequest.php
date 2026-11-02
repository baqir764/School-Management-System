<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;

class StoreClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('academics.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'numeric_level' => ['required', 'integer', 'min:0', 'max:100', 'unique:classes,numeric_level'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
