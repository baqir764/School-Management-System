<?php

namespace App\Http\Requests\Teachers;

use Illuminate\Foundation\Http\FormRequest;

class ImportTeachersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('teachers.manage');
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls', 'max:5120'],
        ];
    }
}
