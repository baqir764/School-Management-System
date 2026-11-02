<?php

namespace App\Http\Requests\Fees;

use App\Models\FeeInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateFeeInvoiceDiscountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fees.manage');
    }

    public function rules(): array
    {
        return [
            'discount_amount' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
        ];
    }

    /**
     * A discount can't exceed the invoice's own (undiscounted) amount, and
     * can't be raised so high that the payable amount left over would sit
     * below what's already been collected against this invoice - either
     * one would leave FeeInvoice::balance() reporting a negative balance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('discount_amount')) {
                return;
            }

            /** @var FeeInvoice $invoice */
            $invoice = $this->route('fee_invoice');
            $invoice->loadMissing('payments');

            $discount = (float) $this->input('discount_amount');

            if ($discount > (float) $invoice->amount) {
                $validator->errors()->add('discount_amount', __('app.error_discount_exceeds_amount'));

                return;
            }

            $payable = round((float) $invoice->amount - $discount, 2);

            if ($payable < $invoice->totalPaid()) {
                $validator->errors()->add('discount_amount', __('app.error_discount_below_paid'));
            }
        });
    }
}
