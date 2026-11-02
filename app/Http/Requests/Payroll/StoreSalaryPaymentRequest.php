<?php

namespace App\Http\Requests\Payroll;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\Employee;
use App\Models\SalaryPayment;
use App\Models\Teacher;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreSalaryPaymentRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('payroll.pay');
    }

    /**
     * salary_month has no day component - a Jalali month doesn't line up
     * with a Gregorian one, so it's its own two-part month/year input
     * (see resources/views/salary-payments/create.blade.php) rather than
     * the three-part <x-jalali-date> component used everywhere else.
     */
    public function rules(): array
    {
        return array_merge([
            'payee_type' => ['required', Rule::in(['teacher', 'employee'])],
            'payee_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'salary_month' => ['required', 'array'],
            'salary_month.month' => ['required', 'integer', 'between:1,12'],
            'salary_month.year' => ['required', 'integer', 'between:1300,1500'],
            'payment_method' => ['required', 'string', Rule::in(SalaryPayment::METHODS)],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], $this->jalaliDateRules('payment_date'));
    }

    /**
     * Cross-field checks that a plain rule can't express: the payee must
     * actually exist, and this payee can't already have a payment for the
     * chosen month (see salary_payments' unique (payee_type, payee_id,
     * salary_month) index - this gives a friendly error instead of a raw
     * DB constraint violation).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'payment_date');

            $date = $this->jalaliDateFromInput('payment_date');
            if ($date && $date > JalaliDate::today()) {
                $validator->errors()->add('payment_date.day', __('app.error_payment_date_future'));
            }

            if (! $this->filled('payee_type') || ! $this->filled('payee_id')) {
                return;
            }

            $model = $this->payeeModelClass();

            if (! $model || ! $model::whereKey($this->input('payee_id'))->exists()) {
                $validator->errors()->add('payee_id', __('app.error_invalid_payee'));

                return;
            }

            if ($this->filled('salary_month.month') && $this->filled('salary_month.year') && $this->alreadyPaidForMonth()) {
                $validator->errors()->add('salary_month.month', __('app.error_salary_already_paid'));
            }

            if ($this->filled('discount_amount') && $this->filled('amount')
                && (float) $this->input('discount_amount') > (float) $this->input('amount')) {
                $validator->errors()->add('discount_amount', __('app.error_discount_exceeds_amount'));
            }
        });
    }

    /**
     * The combined "YYYY-MM" Jalali string for the salary_month field -
     * null if either part is missing (which the required rules above
     * would already have rejected by the time a controller calls this).
     */
    public function salaryMonthFromInput(): ?string
    {
        $month = $this->input('salary_month.month');
        $year = $this->input('salary_month.year');

        if (! $month || ! $year) {
            return null;
        }

        return sprintf('%04d-%02d', (int) $year, (int) $month);
    }

    private function payeeModelClass(): ?string
    {
        return match ($this->input('payee_type')) {
            'teacher' => Teacher::class,
            'employee' => Employee::class,
            default => null,
        };
    }

    private function alreadyPaidForMonth(): bool
    {
        return SalaryPayment::query()
            ->where('payee_type', $this->input('payee_type'))
            ->where('payee_id', $this->input('payee_id'))
            ->where('salary_month', $this->salaryMonthFromInput())
            ->exists();
    }
}
