<?php

namespace App\Http\Requests\Fees;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\FeeInvoice;
use App\Models\FeePayment;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeePaymentRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('fees.collect');
    }

    public function rules(): array
    {
        return array_merge([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999.99'],
            'payment_method' => ['required', 'string', Rule::in(FeePayment::METHODS)],
            'remarks' => ['nullable', 'string', 'max:255'],
        ], $this->jalaliDateRules('payment_date'));
    }

    /**
     * Cross-field checks against the invoice's live balance - amount alone
     * can't be validated with a plain rule since the ceiling depends on
     * how much this invoice already has paid against it.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'payment_date');

            $date = $this->jalaliDateFromInput('payment_date');
            if ($date && $date > JalaliDate::today()) {
                $validator->errors()->add('payment_date.day', __('app.error_payment_date_future'));
            }

            $balance = $this->invoiceBalance();

            if ($balance <= 0) {
                $validator->errors()->add('amount', __('app.error_fee_invoice_already_paid'));

                return;
            }

            if ($this->filled('amount') && (float) $this->input('amount') > $balance) {
                $validator->errors()->add('amount', __('app.error_fee_payment_exceeds_balance'));
            }
        });
    }

    private function invoiceBalance(): float
    {
        /** @var FeeInvoice $invoice */
        $invoice = $this->route('fee_invoice');
        $invoice->loadMissing('payments');

        return $invoice->balance();
    }
}
