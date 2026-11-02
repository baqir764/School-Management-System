<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('timetable.manage');
    }

    public function rules(): array
    {
        return [
            'section_id' => [
                'required', 'integer', 'exists:sections,id',
                // Composite uniqueness: no other row may already occupy
                // this exact section + period + day slot.
                Rule::unique('timetable_entries', 'section_id')->where(fn ($q) => $q
                    ->where('period_id', $this->input('period_id'))
                    ->where('day_of_week', $this->input('day_of_week'))),
            ],
            'period_id' => ['required', 'integer', 'exists:periods,id'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'section_id.unique' => __('app.error_slot_taken'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->isTeacherDoubleBooked()) {
                $validator->errors()->add('teacher_id', __('app.error_teacher_double_booked'));
            }
        });
    }

    private function isTeacherDoubleBooked(): bool
    {
        return \App\Models\TimetableEntry::query()
            ->where('teacher_id', $this->input('teacher_id'))
            ->where('period_id', $this->input('period_id'))
            ->where('day_of_week', $this->input('day_of_week'))
            ->where('section_id', '!=', $this->input('section_id'))
            ->exists();
    }
}
