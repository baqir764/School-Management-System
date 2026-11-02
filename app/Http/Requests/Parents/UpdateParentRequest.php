<?php

namespace App\Http\Requests\Parents;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateParentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('parents.manage');
    }

    public function rules(): array
    {
        $id = $this->route('parent')->id;

        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('parents', 'user_id')->ignore($id)],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'national_id' => ['nullable', 'string', 'max:50'],
            'occupation' => ['nullable', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:190'],
            'address' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ];
    }
}
