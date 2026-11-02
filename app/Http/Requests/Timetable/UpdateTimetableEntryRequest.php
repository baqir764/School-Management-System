<?php

namespace App\Http\Requests\Timetable;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTimetableEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('timetable.manage');
    }

    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['required', 'integer', 'exists:teachers,id'],
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
        $entry = $this->route('timetable_entry');

        return \App\Models\TimetableEntry::query()
            ->where('teacher_id', $this->input('teacher_id'))
            ->where('period_id', $entry->period_id)
            ->where('day_of_week', $entry->day_of_week)
            ->where('section_id', '!=', $entry->section_id)
            ->where('id', '!=', $entry->id)
            ->exists();
    }
}
