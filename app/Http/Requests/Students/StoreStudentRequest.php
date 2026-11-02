<?php

namespace App\Http\Requests\Students;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('students.manage');
    }

    public function rules(): array
    {
        return array_merge([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'grandfather_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['required', 'in:male,female'],
            'place_of_birth' => ['nullable', 'string', 'max:150'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'string', 'max:100'],
            'blood_group' => ['nullable', 'string', 'max:5'],
            'address' => ['nullable', 'string', 'max:255'],
            'province' => ['nullable', 'string', 'max:100'],
            'district' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'photo' => ['nullable', 'image', 'max:2048'],
            'status' => ['required', 'in:active,inactive,graduated,transferred,expelled'],
            'previous_school' => ['nullable', 'string', 'max:190'],
            'medical_notes' => ['nullable', 'string', 'max:2000'],
        ], $this->jalaliDateRules('date_of_birth', required: false), $this->jalaliDateRules('admission_date', required: true));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'date_of_birth', required: false);
            $this->validateJalaliDate($validator, 'admission_date', required: true);

            if ($this->filled('date_of_birth.year') && $this->filled('date_of_birth.month') && $this->filled('date_of_birth.day')) {
                $dob = $this->jalaliDateFromInput('date_of_birth');
                if ($dob && ! \App\Support\JalaliDate::isPast($dob)) {
                    $validator->errors()->add('date_of_birth.day', __('app.error_date_of_birth_must_be_past'));
                }
            }
        });
    }
}
