<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\ExamSubject;
use App\Models\GradeScale;
use App\Models\Mark;
use App\Models\ParentGuardian;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Everything a parent sees about their own children: current
 * class/section, attendance summary + recent history, published exam
 * results with per-subject marks, and the children's teachers by name
 * (homeroom + subject teachers). Every query is scoped to the parent's
 * linked students only - one family can never see another's data.
 */
class ParentOverviewService
{
    /**
     * @return array<int, array{student: Student, enrollment: ?object, class_label: ?string, homeroom_teacher: ?object, attendance: array, exams: array, teachers: Collection}>|null
     */
    public static function forUser(User $user): ?array
    {
        if ($user->hasAnyRole(['Super Administrator', 'School Administrator'])) {
            return null;
        }

        // Avoid lazy-loading $user->parentGuardian under
        // Model::shouldBeStrict().
        $guardian = ParentGuardian::query()->where('user_id', $user->id)->first();

        if (! $guardian) {
            return null;
        }

        $children = $guardian->students()->orderBy('first_name')->get();

        if ($children->isEmpty()) {
            return null;
        }

        return $children->map(fn (Student $student) => self::childOverview($student))->all();
    }

    /**
     * Teachers a parent may address: subject teachers of their children's
     * current classes plus homeroom teachers. Used by the announcement
     * compose form and by sender-side validation, so a parent can only
     * ever message the "relevant" teachers, never the whole staff.
     *
     * @return Collection<int, array{teacher: object, subjects: array<int, string>, children: array<int, string>}>
     */
    public static function teacherOptions(User $user): Collection
    {
        $overview = self::forUser($user);

        if (! $overview) {
            return collect();
        }

        $options = [];

        foreach ($overview as $child) {
            foreach ($child['teachers'] as $entry) {
                $id = $entry['teacher']->id;

                if (! isset($options[$id])) {
                    $options[$id] = [
                        'teacher' => $entry['teacher'],
                        'subjects' => [],
                        'children' => [],
                    ];
                }

                $options[$id]['subjects'] = array_values(array_unique(array_merge(
                    $options[$id]['subjects'],
                    $entry['subjects']
                )));

                if (! in_array($child['student']->fullName(), $options[$id]['children'], true)) {
                    $options[$id]['children'][] = $child['student']->fullName();
                }
            }
        }

        return collect(array_values($options));
    }

    /**
     * @return array<int, int>
     */
    public static function teacherIds(User $user): array
    {
        return self::teacherOptions($user)->map(fn (array $o) => (int) $o['teacher']->id)->all();
    }

    /**
     * @return array{student: Student, enrollment: ?object, class_label: ?string, homeroom_teacher: ?object, attendance: array, exams: array, teachers: Collection}
     */
    private static function childOverview(Student $student): array
    {
        $year = AcademicYear::current();

        $enrollment = $student->enrollments()
            ->with(['classRoom', 'section.classTeacher', 'section.classRoom'])
            ->when($year, fn ($q) => $q->where('academic_year_id', $year->id))
            ->active()
            ->orderByDesc('id')
            ->first();

        return [
            'student' => $student,
            'enrollment' => $enrollment,
            'class_label' => $enrollment?->section?->label() ?? $enrollment?->classRoom?->name,
            'homeroom_teacher' => $enrollment?->section?->classTeacher,
            'attendance' => self::attendanceSummary($student, $year?->id),
            'exams' => self::examResults($student, $enrollment),
            'teachers' => self::childTeachers($enrollment),
        ];
    }

    /**
     * @return array{present: int, absent: int, late: int, excused: int, total: int, recent: Collection}
     */
    private static function attendanceSummary(Student $student, ?int $academicYearId): array
    {
        $base = AttendanceRecord::query()
            ->where('student_id', $student->id)
            ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId));

        $counts = (clone $base)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        // Jalali dates are zero-padded "YYYY-MM-DD" strings, so plain
        // string ordering is chronological (see JalaliDate).
        $recent = (clone $base)->orderByDesc('date')->orderByDesc('id')->limit(10)->get();

        return [
            'present' => (int) ($counts['present'] ?? 0),
            'absent' => (int) ($counts['absent'] ?? 0),
            'late' => (int) ($counts['late'] ?? 0),
            'excused' => (int) ($counts['excused'] ?? 0),
            'total' => (int) $counts->sum(),
            'recent' => $recent,
        ];
    }

    /**
     * Published exams of the child's class/year with this student's
     * per-subject marks - the same total/percentage/grade/status math as
     * MarksController::results(), but for one student.
     */
    private static function examResults(Student $student, ?object $enrollment): array
    {
        if (! $enrollment) {
            return [];
        }

        $exams = Exam::query()
            ->where('class_id', $enrollment->class_id)
            ->where('academic_year_id', $enrollment->academic_year_id)
            ->where('is_published', true)
            ->with(['examSubjects.subject.teacher'])
            ->orderBy('start_date')
            ->get();

        if ($exams->isEmpty()) {
            return [];
        }

        $marks = Mark::query()
            ->where('student_id', $student->id)
            ->whereIn('exam_subject_id', $exams->flatMap->examSubjects->pluck('id'))
            ->get()
            ->keyBy('exam_subject_id');

        return $exams->map(function (Exam $exam) use ($marks) {
            $totalObtained = 0.0;
            $totalFull = 0;
            $hasIncomplete = false;
            $hasFail = false;

            $subjects = $exam->examSubjects->map(function (ExamSubject $examSubject) use ($marks, &$totalObtained, &$totalFull, &$hasIncomplete, &$hasFail) {
                $totalFull += $examSubject->full_marks;
                /** @var Mark|null $mark */
                $mark = $marks->get($examSubject->id);

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
                'exam' => $exam,
                'subjects' => $subjects,
                'total_obtained' => $totalObtained,
                'total_full' => $totalFull,
                'percentage' => $percentage,
                'grade' => GradeScale::forPercentage($percentage)?->grade_name,
                'status' => $hasIncomplete ? 'incomplete' : ($hasFail ? 'fail' : 'pass'),
            ];
        })->all();
    }

    /**
     * Homeroom teacher plus active subject teachers of the child's class,
     * each with the subject names they teach the child.
     *
     * @return Collection<int, array{teacher: object, subjects: array<int, string>, is_homeroom: bool}>
     */
    private static function childTeachers(?object $enrollment): Collection
    {
        $teachers = collect();

        $homeroom = $enrollment?->section?->classTeacher;

        if ($homeroom) {
            $teachers->push([
                'teacher' => $homeroom,
                'subjects' => [],
                'is_homeroom' => true,
            ]);
        }

        if (! $enrollment) {
            return $teachers;
        }

        $subjects = Subject::query()
            ->where('class_id', $enrollment->class_id)
            ->active()
            ->with('teacher')
            ->orderBy('name')
            ->get();

        foreach ($subjects as $subject) {
            if (! $subject->teacher) {
                continue;
            }

            $existing = $teachers->firstWhere(fn (array $e) => (int) $e['teacher']->id === (int) $subject->teacher->id);

            if ($existing) {
                $existing['subjects'][] = $subject->name;
                $teachers = $teachers->map(fn (array $e) => (int) $e['teacher']->id === (int) $subject->teacher->id
                    ? array_merge($e, ['subjects' => array_values(array_unique($existing['subjects']))])
                    : $e);
            } else {
                $teachers->push([
                    'teacher' => $subject->teacher,
                    'subjects' => [$subject->name],
                    'is_homeroom' => (int) ($homeroom?->id ?? 0) === (int) $subject->teacher->id,
                ]);
            }
        }

        return $teachers->values();
    }
}
