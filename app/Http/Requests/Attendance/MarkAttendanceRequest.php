<?php

namespace App\Http\Requests\Attendance;

use App\Http\Requests\Concerns\ValidatesJalaliDates;
use App\Models\AttendanceRecord;
use App\Support\JalaliDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class MarkAttendanceRequest extends FormRequest
{
    use ValidatesJalaliDates;

    public function authorize(): bool
    {
        return $this->user()->can('attendance.mark');
    }

    public function rules(): array
    {
        return array_merge([
            'section_id' => ['required', 'integer', 'exists:sections,id'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'statuses' => ['required', 'array', 'min:1'],
            'statuses.*' => ['required', 'string', Rule::in(AttendanceRecord::STATUSES)],
            'remarks' => ['array'],
            'remarks.*' => ['nullable', 'string', 'max:255'],
            'time_in' => ['array'],
            'time_in.*' => ['nullable', 'date_format:H:i'],
            'time_out' => ['array'],
            'time_out.*' => ['nullable', 'date_format:H:i'],
        ], $this->jalaliDateRules('date'));
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->sectionIsAccessible()) {
                $validator->errors()->add('section_id', __('app.error_not_your_section'));
            }

            $this->validateJalaliDate($validator, 'date');

            $date = $this->jalaliDateFromInput('date');
            if ($date && $date > JalaliDate::today()) {
                $validator->errors()->add('date.day', __('app.error_attendance_date_future'));
            }

            // Time-out without time-in, or an out earlier than the in, is
            // always a typo - flag it per student instead of saving it.
            foreach ((array) $this->input('time_in', []) as $studentId => $in) {
                $out = $this->input("time_out.{$studentId}");
                if (($out === null || $out === '') || ($in === null || $in === '')) {
                    continue;
                }
                if ($out < $in) {
                    $validator->errors()->add(
                        "time_out.{$studentId}",
                        __('app.error_time_out_before_in')
                    );
                }
            }
        });
    }

    /**
     * School Administrator / Super Administrator / Attendance Clerk can
     * mark attendance for any section; a Teacher may only mark it for
     * section(s) where they are the assigned homeroom (class) teacher.
     */
    private function sectionIsAccessible(): bool
    {
        $user = $this->user();

        if ($user->hasAnyRole(['Super Administrator', 'School Administrator', 'Attendance Clerk'])) {
            return true;
        }

        return $user->teacher?->homeroomSections()->where('id', $this->input('section_id'))->exists() ?? false;
    }
}
