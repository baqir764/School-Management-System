<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\StoreSectionRequest;
use App\Http\Requests\Academics\UpdateSectionRequest;
use App\Models\ClassRoom;
use App\Models\Section;
use App\Models\Teacher;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SectionController extends Controller
{
    public function index(): View
    {
        return view('sections.index', [
            // select('sections.*') must run before withCount(): select()
            // replaces the query's whole column list, so calling it after
            // withCount() would silently wipe out the enrollments_count
            // column withCount() adds - which is exactly what happened here.
            'sections' => Section::query()
                ->join('classes', 'classes.id', '=', 'sections.class_id')
                ->select('sections.*')
                ->with(['classRoom', 'classTeacher'])
                ->withCount('enrollments')
                ->orderBy('classes.numeric_level')
                ->orderBy('sections.name')
                ->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('sections.create', $this->formData());
    }

    public function store(StoreSectionRequest $request): RedirectResponse
    {
        $section = Section::create($request->validated());

        AuditLogger::log('create', 'sections', $section->id, null, $section->only(['class_id', 'name']));

        return redirect()->route('sections.index')->with('status', __('app.section_created'));
    }

    public function edit(Section $section): View
    {
        return view('sections.edit', array_merge($this->formData(), ['section' => $section]));
    }

    public function update(UpdateSectionRequest $request, Section $section): RedirectResponse
    {
        $original = $section->only(['class_id', 'name', 'class_teacher_id']);

        $section->update($request->validated());

        AuditLogger::log('update', 'sections', $section->id, $original, $section->only(['class_id', 'name', 'class_teacher_id']));

        return redirect()->route('sections.index')->with('status', __('app.section_updated'));
    }

    public function destroy(Section $section): RedirectResponse
    {
        // Sections no longer soft-delete (see the drop_soft_deletes
        // migration), so delete() below is permanent. timetable_entries
        // and attendance_records cascade-delete on section_id, and were
        // previously unchecked here because a soft-deleted section never
        // actually triggered that cascade - now that it does, deleting a
        // section that still has timetable or attendance history would
        // silently destroy it, so both are blocked the same way enrollments
        // already were.
        abort_if(
            $section->enrollments()->exists()
                || $section->timetableEntries()->exists()
                || $section->attendanceRecords()->exists(),
            403,
            __('app.cannot_delete_section_in_use')
        );

        $section->delete();

        AuditLogger::log('delete', 'sections', $section->id);

        return redirect()->route('sections.index')->with('status', __('app.section_deleted'));
    }

    private function formData(): array
    {
        return [
            'classes' => ClassRoom::query()->ordered()->get(),
            'teachers' => Teacher::query()->active()->orderBy('first_name')->get(),
        ];
    }
}
