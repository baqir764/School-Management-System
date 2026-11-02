<?php

namespace App\Http\Requests\Exams;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\Exam;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateExamRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('exams.manage');
    }

    public function rules(): array
    {
        return array_merge([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['required', 'integer', 'exists:classes,id'],
            'name' => ['required', 'string', 'max:150'],
            'term_type' => ['required', 'string', Rule::in(Exam::TERM_TYPES)],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ], $this->jalaliDateRules('start_date'), $this->jalaliDateRules('end_date'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'start_date');
            $this->validateJalaliDate($validator, 'end_date');

            $start = $this->jalaliDateFromInput('start_date');
            $end = $this->jalaliDateFromInput('end_date');

            if ($start && $end && $end < $start) {
                $validator->errors()->add('end_date.day', __('app.error_end_date_after_start_date'));
            }
        });
    }
}
