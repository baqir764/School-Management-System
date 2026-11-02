<?php

namespace App\Http\Requests\Payroll;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEmployeeRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('payroll.manage');
    }

    public function rules(): array
    {
        return array_merge([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'shift_id' => ['nullable', 'integer', 'exists:shifts,id'],
            'phone' => ['nullable', 'string', 'max:30'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'monthly_salary' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'status' => ['required', Rule::in(Employee::STATUSES)],
        ], $this->jalaliDateRules('joining_date', required: false));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'joining_date', required: false);
        });
    }
}
