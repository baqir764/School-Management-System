<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;

class StoreSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('academics.manage');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'code' => ['required', 'string', 'max:30', 'unique:subjects,code'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['required', 'integer', 'min:0', 'lte:full_marks'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
