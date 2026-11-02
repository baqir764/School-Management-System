<?php

namespace App\Http\Requests\Exams;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\Mark;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateExamSubjectRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('exams.manage');
    }

    public function rules(): array
    {
        return array_merge([
            'full_marks' => ['required', 'integer', 'min:1', 'max:1000'],
            'pass_marks' => ['required', 'integer', 'min:0', 'lte:full_marks'],
        ], $this->jalaliDateRules('exam_date', required: false));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'exam_date', required: false);

            // Lowering full_marks below a mark that's already been
            // recorded would leave that mark inconsistent with its own
            // exam-subject (and silently wrong in every percentage/grade
            // calculation derived from it) - refuse rather than allow it.
            $highestMarkObtained = Mark::query()
                ->where('exam_subject_id', $this->route('exam_subject')->id)
                ->max('marks_obtained');

            if ($highestMarkObtained !== null && $this->integer('full_marks') < $highestMarkObtained) {
                $validator->errors()->add('full_marks', __('app.error_full_marks_below_existing_marks', ['marks' => $highestMarkObtained]));
            }
        });
    }
}
