<?php

namespace App\Http\Requests\Academics;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreAcademicYearRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('academics.manage');
    }

    public function rules(): array
    {
        return array_merge([
            'name' => ['required', 'string', 'max:50', 'unique:academic_years,name'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ], $this->jalaliDateRules('start_date'), $this->jalaliDateRules('end_date'));
    }

    /**
     * "end after start" can't be a plain 'after:start_date' rule once
     * both are Jalali strings submitted as day/month/year arrays -
     * compared directly as strings once combined, which is safe because
     * both are always zero-padded "YYYY-MM-DD".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'start_date');
            $this->validateJalaliDate($validator, 'end_date');

            $start = $this->jalaliDateFromInput('start_date');
            $end = $this->jalaliDateFromInput('end_date');

            if ($start && $end && $end <= $start) {
                $validator->errors()->add('end_date.day', __('app.error_end_date_after_start_date'));
            }
        });
    }
}
