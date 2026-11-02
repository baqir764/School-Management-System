<?php

namespace App\Http\Requests\Students;

use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Validates a within-year transfer: moving a student's existing
 * enrollment to a different class and/or section of the SAME academic
 * year. The academic year and enrollment date are deliberately NOT
 * editable here - promotion to a new year is a new enrollment row (see
 * StoreEnrollmentRequest), never an edit of this one.
 */
class UpdateEnrollmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('students.manage');
    }

    public function rules(): array
    {
        /** @var \App\Models\Enrollment $enrollment */
        $enrollment = $this->route('enrollment');

        return [
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'roll_number' => [
                'nullable', 'string', 'max:20',
                Rule::unique('enrollments', 'roll_number')
                    ->ignore($enrollment->id)
                    ->where(fn ($query) => $query
                        ->where('academic_year_id', $enrollment->academic_year_id)
                        ->where('section_id', $this->input('section_id'))
                        ->whereNull('deleted_at')),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->has('class_id') || $validator->errors()->has('section_id')) {
                return;
            }

            $section = Section::query()->find($this->input('section_id'));

            if ($section && (int) $section->class_id !== (int) $this->input('class_id')) {
                $validator->errors()->add('section_id', __('app.error_section_not_in_class'));
            }
        });
    }

    public function messages(): array
    {
        return [
            'roll_number.unique' => __('app.error_roll_number_taken'),
        ];
    }
}
