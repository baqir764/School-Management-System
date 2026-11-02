<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\Section;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEnrollmentRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('students.manage');
    }

    public function rules(): array
    {
        $studentId = $this->route('student')->id;

        return array_merge([
            'academic_year_id' => [
                'required', 'integer', 'exists:academic_years,id',
                // Soft-deleted (legacy trashed) rows must not block a fresh
                // enrollment: removals are permanent now (forceDelete), so a
                // trashed row can only be left over from before that change.
                Rule::unique('enrollments', 'academic_year_id')->where(fn ($query) => $query
                    ->where('student_id', $studentId)
                    ->whereNull('deleted_at')),
            ],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'roll_number' => [
                'nullable', 'string', 'max:20',
                Rule::unique('enrollments', 'roll_number')->where(fn ($query) => $query
                    ->where('academic_year_id', $this->input('academic_year_id'))
                    ->where('section_id', $this->input('section_id'))
                    ->whereNull('deleted_at')),
            ],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], $this->jalaliDateRules('enrollment_date'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'enrollment_date');

            $this->validateSectionBelongsToClass($validator);
        });
    }

    /**
     * The chosen section must actually belong to the chosen class - the
     * form filters the dropdown with JS, but that must never be the only
     * enforcement (a crafted request could otherwise place a student into
     * a section of a different class).
     */
    protected function validateSectionBelongsToClass(Validator $validator): void
    {
        if ($validator->errors()->has('class_id') || $validator->errors()->has('section_id')) {
            return;
        }

        $section = Section::query()->find($this->input('section_id'));

        if ($section && (int) $section->class_id !== (int) $this->input('class_id')) {
            $validator->errors()->add('section_id', __('app.error_section_not_in_class'));
        }
    }

    public function messages(): array
    {
        return [
            'academic_year_id.unique' => __('app.error_student_already_enrolled_this_year'),
            'roll_number.unique' => __('app.error_roll_number_taken'),
        ];
    }
}
