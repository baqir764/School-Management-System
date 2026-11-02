<?php

namespace App\Http\Requests\Fees;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreFeeInvoiceGenerationRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('fees.manage');
    }

    public function rules(): array
    {
        return $this->jalaliDateRules('due_date', required: false);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $this->validateJalaliDate($validator, 'due_date', required: false);
        });
    }
}
