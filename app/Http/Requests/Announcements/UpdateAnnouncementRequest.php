<?php

namespace App\Http\Requests\Announcements;

use App\Services\ParentOverviewService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('announcements.manage')
            || $this->user()->can('announcements.send');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // Senders (parents) re-address only among their own children's
        // teachers - see StoreAnnouncementRequest for why.
        if (! $this->user()->can('announcements.manage')) {
            return [
                'title' => ['required', 'string', 'max:255'],
                'body' => ['required', 'string', 'max:5000'],
                'teacher_ids' => ['required', 'array', 'min:1'],
                'teacher_ids.*' => ['integer'],
            ];
        }

        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', Rule::in($this->availableRoles())],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        if ($this->user()->can('announcements.manage')) {
            return;
        }

        $validator->after(function (Validator $validator) {
            $allowed = ParentOverviewService::teacherIds($this->user());

            foreach ((array) $this->input('teacher_ids', []) as $id) {
                if (! in_array((int) $id, $allowed, true)) {
                    $validator->errors()->add('teacher_ids', __('app.error_not_your_child_teacher'));

                    return;
                }
            }
        });
    }

    public function availableRoles(): array
    {
        return ['Teacher', 'Parent', 'Student', 'Librarian', 'Storekeeper', 'Receptionist', 'Accountant', 'Principal'];
    }
}
