<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\StoreExamSubjectRequest;
use App\Http\Requests\Exams\UpdateExamSubjectRequest;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ExamSubjectController extends Controller
{
    public function store(StoreExamSubjectRequest $request, Exam $exam): RedirectResponse
    {
        $data = collect($request->validated())->except('exam_date')->all();
        $data['exam_date'] = $request->jalaliDateFromInput('exam_date');

        $examSubject = $exam->examSubjects()->create($data);

        AuditLogger::log('create', 'exam_subjects', $examSubject->id, null, $examSubject->only(['exam_id', 'subject_id', 'full_marks', 'pass_marks']));

        return redirect()->route('exams.show', $exam)->with('status', __('app.exam_subject_added'));
    }

    /**
     * Subject itself (which subject this is) is never editable here -
     * only its per-exam settings (full/pass marks, exam date). Changing
     * which subject an already-graded row points to would silently
     * detach it from the marks already entered against it; removing and
     * re-adding is the (already-guarded, see destroy()) path for that.
     */
    public function edit(ExamSubject $exam_subject): View
    {
        $exam_subject->load(['exam', 'subject']);

        return view('exam-subjects.edit', ['examSubject' => $exam_subject]);
    }

    public function update(UpdateExamSubjectRequest $request, ExamSubject $exam_subject): RedirectResponse
    {
        $before = $exam_subject->only(['full_marks', 'pass_marks', 'exam_date']);

        $exam_subject->update([
            'full_marks' => $request->integer('full_marks'),
            'pass_marks' => $request->integer('pass_marks'),
            'exam_date' => $request->jalaliDateFromInput('exam_date'),
        ]);

        AuditLogger::log('update', 'exam_subjects', $exam_subject->id, $before, $exam_subject->only(['full_marks', 'pass_marks', 'exam_date']));

        return redirect()->route('exams.show', $exam_subject->exam)->with('status', __('app.exam_subject_updated'));
    }

    public function destroy(ExamSubject $exam_subject): RedirectResponse
    {
        $exam = $exam_subject->exam;

        abort_if($exam_subject->marks()->exists(), 403, __('app.cannot_remove_exam_subject_in_use'));

        $exam_subject->delete();

        AuditLogger::log('delete', 'exam_subjects', $exam_subject->id);

        return redirect()->route('exams.show', $exam)->with('status', __('app.exam_subject_removed'));
    }
}
