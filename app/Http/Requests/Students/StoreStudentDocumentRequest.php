<?php

namespace App\Http\Requests\Students;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('students.manage');
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'in:birth_certificate,national_id,transfer_certificate,photo,other'],
            'title' => ['required', 'string', 'max:190'],
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,doc,docx'],
        ];
    }
}
