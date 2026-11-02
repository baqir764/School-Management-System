<?php

namespace App\Http\Requests\Academics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('academics.manage');
    }

    public function rules(): array
    {
        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'name' => [
                'required', 'string', 'max:20',
                Rule::unique('sections', 'name')->where(fn ($query) => $query->where('class_id', $this->input('class_id'))),
            ],
            'class_teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:500'],
            'room_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
