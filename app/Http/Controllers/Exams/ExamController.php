<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\StoreExamRequest;
use App\Http\Requests\Exams\UpdateExamRequest;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\Exam;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function index(Request $request): View
    {
        $academicYearId = $request->integer('academic_year_id') ?: AcademicYear::current()?->id;

        return view('exams.index', [
            'exams' => Exam::query()
                ->with(['academicYear', 'classRoom'])
                ->withCount('examSubjects')
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->orderByDesc('start_date')
                ->paginate(20)
                ->withQueryString(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'selectedAcademicYearId' => $academicYearId,
        ]);
    }

    public function create(): View
    {
        return view('exams.create', $this->formData());
    }

    public function store(StoreExamRequest $request): RedirectResponse
    {
        $data = collect($request->validated())->except(['start_date', 'end_date'])->all();
        $data['start_date'] = $request->jalaliDateFromInput('start_date');
        $data['end_date'] = $request->jalaliDateFromInput('end_date');

        $exam = Exam::create($data);

        AuditLogger::log('create', 'exams', $exam->id, null, $exam->only(['name', 'class_id', 'academic_year_id', 'term_type']));

        return redirect()->route('exams.show', $exam)->with('status', __('app.exam_created'));
    }

    public function show(Exam $exam): View
    {
        $exam->load(['academicYear', 'classRoom', 'examSubjects.subject']);

        $attachedSubjectIds = $exam->examSubjects->pluck('subject_id');

        return view('exams.show', [
            'exam' => $exam,
            'availableSubjects' => $exam->classRoom->subjects()->active()->orderBy('name')->get()
                ->reject(fn ($subject) => $attachedSubjectIds->contains($subject->id)),
        ]);
    }

    public function edit(Exam $exam): View
    {
        return view('exams.edit', array_merge($this->formData(), ['exam' => $exam]));
    }

    public function update(UpdateExamRequest $request, Exam $exam): RedirectResponse
    {
        $original = $exam->only(['name', 'class_id', 'academic_year_id', 'term_type', 'start_date', 'end_date']);

        $data = collect($request->validated())->except(['start_date', 'end_date'])->all();
        $data['start_date'] = $request->jalaliDateFromInput('start_date');
        $data['end_date'] = $request->jalaliDateFromInput('end_date');

        $exam->update($data);

        AuditLogger::log('update', 'exams', $exam->id, $original, $exam->only(['name', 'class_id', 'academic_year_id', 'term_type', 'start_date', 'end_date']));

        return redirect()->route('exams.show', $exam)->with('status', __('app.exam_updated'));
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        abort_if($exam->examSubjects()->exists(), 403, __('app.cannot_delete_exam_in_use'));

        $exam->delete();

        AuditLogger::log('delete', 'exams', $exam->id);

        return redirect()->route('exams.index')->with('status', __('app.exam_deleted'));
    }

    public function togglePublish(Exam $exam): RedirectResponse
    {
        $exam->update(['is_published' => ! $exam->is_published]);

        AuditLogger::log('update', 'exams', $exam->id, null, ['is_published' => $exam->is_published]);

        return redirect()->route('exams.show', $exam)->with('status', $exam->is_published
            ? __('app.exam_published')
            : __('app.exam_unpublished'));
    }

    private function formData(): array
    {
        return [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => ClassRoom::query()->active()->ordered()->get(),
        ];
    }
}
