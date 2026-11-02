<?php

namespace App\Http\Requests\Exams;

use App\Models\GradeScale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateGradeScaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('exams.manage');
    }

    public function rules(): array
    {
        return [
            'grade_name' => ['required', 'string', 'max:10'],
            'min_percentage' => ['required', 'numeric', 'between:0,100'],
            'max_percentage' => ['required', 'numeric', 'between:0,100', 'gte:min_percentage'],
            'description' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->overlapsExistingBand()) {
                $validator->errors()->add('min_percentage', __('app.error_grade_band_overlap'));
            }
        });
    }

    private function overlapsExistingBand(): bool
    {
        $min = (float) $this->input('min_percentage');
        $max = (float) $this->input('max_percentage');

        if ($min > $max) {
            return false; // already caught by the gte:min_percentage rule
        }

        $scale = $this->route('grade_scale');

        return GradeScale::query()
            ->where('id', '!=', $scale->id)
            ->where('min_percentage', '<=', $max)
            ->where('max_percentage', '>=', $min)
            ->exists();
    }
}
