<?php

namespace App\Http\Requests\Teachers;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTeacherRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('teachers.manage');
    }

    public function rules(): array
    {
        $id = $this->route('teacher')->id;

        return array_merge([
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('teachers', 'user_id')->ignore($id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'gender' => ['nullable', 'in:male,female'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'qualification' => ['nullable', 'string', 'max:190'],
            'specialization' => ['nullable', 'string', 'max:190'],
            'employment_type' => ['required', 'in:full_time,part_time,contract'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'status' => ['required', 'in:active,inactive,terminated'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ], $this->jalaliDateRules('date_of_birth', required: false), $this->jalaliDateRules('joining_date', required: false));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'date_of_birth', required: false);
            $this->validateJalaliDate($validator, 'joining_date', required: false);
        });
    }
}
