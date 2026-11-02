<?php

namespace App\Http\Requests\Parents;

use Illuminate\Foundation\Http\FormRequest;

class LinkStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('parents.manage');
    }

    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'relationship' => ['required', 'in:Father,Mother,Guardian,Other'],
            'is_primary_contact' => ['sometimes', 'boolean'],
        ];
    }
}
