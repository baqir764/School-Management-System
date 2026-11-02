<?php

namespace App\Http\Requests\Fees;

use App\Models\FeeStructure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFeeStructureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fees.manage');
    }

    public function rules(): array
    {
        return [
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'class_id' => ['nullable', 'integer', 'exists:classes,id'],
            'name' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'frequency' => ['required', 'string', Rule::in(FeeStructure::FREQUENCIES)],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->duplicateExists()) {
                $validator->errors()->add('name', __('app.error_fee_structure_duplicate'));
            }
        });
    }

    /**
     * MySQL's unique index treats every NULL class_id as distinct, so the
     * "school-wide" (null class_id) case needs its own duplicate check
     * here rather than relying on the migration's unique constraint alone.
     */
    private function duplicateExists(): bool
    {
        return FeeStructure::query()
            ->where('academic_year_id', $this->input('academic_year_id'))
            ->where('name', $this->input('name'))
            ->when(
                $this->filled('class_id'),
                fn ($q) => $q->where('class_id', $this->input('class_id')),
                fn ($q) => $q->whereNull('class_id')
            )
            ->exists();
    }
}
