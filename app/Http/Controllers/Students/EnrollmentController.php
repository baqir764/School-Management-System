<?php

namespace App\Http\Controllers\Students;

use App\Http\Controllers\Controller;
use App\Http\Requests\Students\StoreEnrollmentRequest;
use App\Http\Requests\Students\UpdateEnrollmentRequest;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EnrollmentController extends Controller
{
    public function create(Student $student): View
    {
        return view('students.enroll', [
            'student' => $student,
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => ClassRoom::query()->ordered()->get(),
            'sections' => Section::query()->with('classRoom')->get(),
        ]);
    }

    public function store(StoreEnrollmentRequest $request, Student $student): RedirectResponse
    {
        $data = collect($request->validated())->except('enrollment_date')->all();
        $data['enrollment_date'] = $request->jalaliDateFromInput('enrollment_date');

        // Belt-and-braces over the request's unique rule: anything can slip
        // past concurrent validation (including trashed rows at cleanup
        // time), and the DB unique index must never surface as a raw 500 to
        // a user who simply double-submits the enrol form.
        if ($student->enrollments()->where('academic_year_id', $data['academic_year_id'])->exists()) {
            return back()->withErrors(['academic_year_id' => __('app.error_student_already_enrolled_this_year')]);
        }

        // Legacy cleanup: removals used to soft-delete, and the DB-level
        // unique index (student_id + academic_year_id) still sees those
        // trashed rows even though validation now ignores them - purge any
        // leftover trashed row for this student/year so the insert below
        // can never hit a raw unique-constraint 500.
        $student->enrollments()->onlyTrashed()
            ->where('academic_year_id', $data['academic_year_id'])
            ->forceDelete();

        $enrollment = $student->enrollments()->create($data + ['status' => 'active']);

        AuditLogger::log('enroll', 'students', $student->id, null, $enrollment->only(['academic_year_id', 'class_id', 'section_id', 'roll_number']));

        return redirect()->route('students.show', $student)->with('status', __('app.student_enrolled'));
    }

    /**
     * Transfer form: moves this enrollment to another class/section within
     * the same academic year (see UpdateEnrollmentRequest for why the year
     * itself is not editable here).
     */
    public function edit(Student $student, Enrollment $enrollment): View
    {
        abort_unless($enrollment->student_id === $student->id, 404);

        return view('students.transfer', [
            'student' => $student,
            'enrollment' => $enrollment->load(['academicYear', 'classRoom', 'section']),
            'classes' => ClassRoom::query()->ordered()->get(),
            'sections' => Section::query()->with('classRoom')->get(),
        ]);
    }

    public function update(UpdateEnrollmentRequest $request, Student $student, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->student_id === $student->id, 404);

        $original = $enrollment->only(['class_id', 'section_id', 'roll_number']);

        $enrollment->update($request->validated());

        AuditLogger::log('transfer', 'students', $student->id,
            $original + ['enrollment_id' => $enrollment->id],
            $enrollment->only(['class_id', 'section_id', 'roll_number']));

        return redirect()->route('students.show', $student)->with('status', __('app.student_transferred'));
    }

    /**
     * Permanently removes this enrollment, freeing the student from that
     * academic year and section entirely (forceDelete, not a soft delete -
     * there is no "trash" for enrollments, and a trashed row would keep
     * blocking re-enrollment into the same year via the DB unique index).
     */
    public function destroy(Student $student, Enrollment $enrollment): RedirectResponse
    {
        abort_unless($enrollment->student_id === $student->id, 404);

        $enrollment->forceDelete();

        AuditLogger::log('unenroll', 'students', $student->id, ['enrollment_id' => $enrollment->id]);

        return back()->with('status', __('app.enrollment_removed'));
    }
}
