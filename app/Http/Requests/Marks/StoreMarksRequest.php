<?php

namespace App\Http\Requests\Marks;

use App\Models\ExamSubject;
use App\Models\Subject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMarksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('marks.enter');
    }

    public function rules(): array
    {
        return [
            'marks' => ['required', 'array', 'min:1'],
            'marks.*' => ['nullable', 'numeric', 'min:0'],
            'absent' => ['array'],
            'absent.*' => ['nullable', 'boolean'],
            'remarks' => ['array'],
            'remarks.*' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /** @var ExamSubject $examSubject */
            $examSubject = $this->route('exam_subject');

            if (! $this->subjectIsAccessible($examSubject)) {
                $validator->errors()->add('marks', __('app.error_not_your_subject'));

                return;
            }

            foreach ((array) $this->input('marks', []) as $studentId => $value) {
                if ($value !== null && $value !== '' && (float) $value > (float) $examSubject->full_marks) {
                    $validator->errors()->add("marks.{$studentId}", __('app.error_marks_exceed_full'));
                }
            }
        });
    }

    /**
     * School Administrator/Super Administrator can enter marks for any
     * subject; a Teacher may only enter marks for a subject they are
     * assigned to teach (Subject.teacher_id). Looked up via the raw
     * subject_id/teacher_id columns - not the ->subject relation - so no
     * eager load is required here under this app's strict Eloquent mode.
     */
    private function subjectIsAccessible(ExamSubject $examSubject): bool
    {
        $user = $this->user();

        if ($user->hasAnyRole(['Super Administrator', 'School Administrator'])) {
            return true;
        }

        $subjectTeacherId = Subject::query()->where('id', $examSubject->subject_id)->value('teacher_id');

        return $subjectTeacherId !== null && $subjectTeacherId === $user->teacher?->id;
    }
}
