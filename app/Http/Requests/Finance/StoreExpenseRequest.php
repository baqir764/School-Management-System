<?php

namespace App\Http\Requests\Finance;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\Expense;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExpenseRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('finance.record');
    }

    public function rules(): array
    {
        return array_merge([
            'expense_category_id' => ['required', 'integer', 'exists:expense_categories,id'],
            'title' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'payment_method' => ['required', 'string', Rule::in(Expense::METHODS)],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], $this->jalaliDateRules('expense_date'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'expense_date');

            $date = $this->jalaliDateFromInput('expense_date');
            if ($date && $date > JalaliDate::today()) {
                $validator->errors()->add('expense_date.day', __('app.error_payment_date_future'));
            }
        });
    }
}
