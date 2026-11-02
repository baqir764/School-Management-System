<?php

namespace App\Http\Requests\Exams;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExamSubjectRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('exams.manage');
    }

    public function rules(): array
    {
        return array_merge([
            'subject_id' => [
                'required', 'integer', 'exists:subjects,id',
                Rule::unique('exam_subjects', 'subject_id')->where(fn ($q) => $q
                    ->where('exam_id', $this->route('exam')->id)),
            ],
            'full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['required', 'integer', 'min:0', 'lte:full_marks'],
        ], $this->jalaliDateRules('exam_date', required: false));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'exam_date', required: false);
        });
    }

    public function messages(): array
    {
        return [
            'subject_id.unique' => __('app.error_subject_already_in_exam'),
        ];
    }
}
