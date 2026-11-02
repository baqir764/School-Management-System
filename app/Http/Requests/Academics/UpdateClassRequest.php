<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('academics.manage');
    }

    public function rules(): array
    {
        $id = $this->route('class')->id;

        return [
            'name' => ['required', 'string', 'max:100'],
            'numeric_level' => ['required', 'integer', 'min:0', 'max:100', Rule::unique('classes', 'numeric_level')->ignore($id)],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
