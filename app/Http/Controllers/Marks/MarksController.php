<?php

namespace App\Http\Controllers\Marks;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marks\StoreMarksRequest;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\Subject;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class MarksController extends Controller
{
    /**
     * Marks entry grid for one exam-subject: every student enrolled in
     * the exam's class/year, with any marks already entered prefilled.
     */
    public function index(ExamSubject $exam_subject): View
    {
        $exam_subject->load(['exam.classRoom', 'exam.academicYear', 'subject']);

        $this->assertAccessible($exam_subject);

        $students = $exam_subject->exam->enrolledStudents()->get();

        $marks = Mark::query()
            ->where('exam_subject_id', $exam_subject->id)
            ->get()
            ->keyBy('student_id')
            ->each(fn (Mark $mark) => $mark->setRelation('examSubject', $exam_subject));

        return view('marks.index', [
            'examSubject' => $exam_subject,
            'students' => $students,
            'marks' => $marks,
        ]);
    }

    public function store(StoreMarksRequest $request, ExamSubject $exam_subject): RedirectResponse
    {
        $this->assertAccessible($exam_subject);

        $data = $request->validated();

        foreach ($data['marks'] as $studentId => $value) {
            Mark::updateOrCreate(
                [
                    'exam_subject_id' => $exam_subject->id,
                    'student_id' => $studentId,
                ],
                [
                    'marks_obtained' => ($value === '' || $value === null) ? null : $value,
                    'is_absent' => (bool) ($data['absent'][$studentId] ?? false),
                    'remarks' => $data['remarks'][$studentId] ?? null,
                    'entered_by' => $request->user()->id,
                ]
            );
        }

        AuditLogger::log('enter', 'marks', $exam_subject->id, null, [
            'exam_subject_id' => $exam_subject->id,
            'student_count' => count($data['marks']),
        ]);

        return redirect()->route('marks.index', $exam_subject)->with('status', __('app.marks_saved'));
    }

    /**
     * Per-student result summary across every subject of an exam: total,
     * percentage, letter grade (via GradeScale) and an overall pass/fail/
     * incomplete status.
     */
    public function results(Exam $exam): View
    {
        $exam->load(['classRoom', 'academicYear', 'examSubjects.subject']);

        $students = $exam->enrolledStudents()->get();

        $marksByStudent = Mark::query()
            ->whereIn('exam_subject_id', $exam->examSubjects->pluck('id'))
            ->get()
            ->groupBy('student_id');

        $rows = $students->map(function ($student) use ($exam, $marksByStudent) {
            $studentMarks = ($marksByStudent->get($student->id) ?? collect())->keyBy('exam_subject_id');

            $totalObtained = 0.0;
            $totalFull = 0;
            $hasIncomplete = false;
            $hasFail = false;

            $subjectRows = $exam->examSubjects->map(function (ExamSubject $examSubject) use ($studentMarks, &$totalObtained, &$totalFull, &$hasIncomplete, &$hasFail) {
                $totalFull += $examSubject->full_marks;
                $mark = $studentMarks->get($examSubject->id);

                if (! $mark) {
                    $hasIncomplete = true;

                    return ['examSubject' => $examSubject, 'mark' => null];
                }

                $mark->setRelation('examSubject', $examSubject);

                if ($mark->is_absent || ! $mark->isPassing()) {
                    $hasFail = true;
                }

                $totalObtained += (float) ($mark->marks_obtained ?? 0);

                return ['examSubject' => $examSubject, 'mark' => $mark];
            });

            $percentage = $totalFull > 0 ? round(($totalObtained / $totalFull) * 100, 2) : null;

            return [
                'student' => $student,
                'subjects' => $subjectRows,
                'total_obtained' => $totalObtained,
                'total_full' => $totalFull,
                'percentage' => $percentage,
                'grade' => GradeScale::forPercentage($percentage),
                'status' => $hasIncomplete ? 'incomplete' : ($hasFail ? 'fail' : 'pass'),
            ];
        });

        return view('exams.results', [
            'exam' => $exam,
            'rows' => $rows,
        ]);
    }

    /**
     * Mirrors StoreMarksRequest's own accessibility check for the GET/view
     * side - a Teacher may only open the marks grid for a subject they
     * teach; School Administrator/Super Administrator see every subject.
     */
    private function assertAccessible(ExamSubject $examSubject): void
    {
        $user = request()->user();

        if ($user->hasAnyRole(['Super Administrator', 'School Administrator'])) {
            return;
        }

        $subjectTeacherId = Subject::query()->where('id', $examSubject->subject_id)->value('teacher_id');

        abort_if($subjectTeacherId === null || $subjectTeacherId !== $user->teacher?->id, 403, __('app.error_not_your_subject'));
    }
}
