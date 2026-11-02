<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\StoreTimetableEntryRequest;
use App\Http\Requests\Timetable\UpdateTimetableEntryRequest;
use App\Models\Period;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TimetableEntry;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableEntryController extends Controller
{
    /**
     * Days the school actually holds classes, in display order. Afghan
     * school week runs Saturday - Thursday (Friday is the weekend), so the
     * grid is ordered that way even though day_of_week itself still stores
     * the standard ISO-8601 value (1 = Monday .. 7 = Sunday) - this keeps
     * the schema locale-neutral while the UI reflects local convention.
     */
    public const DISPLAY_DAYS = [6, 7, 1, 2, 3, 4];

    public function index(Request $request): View
    {
        $sections = Section::query()
            ->join('classes', 'classes.id', '=', 'sections.class_id')
            ->select('sections.*')
            ->with('classRoom')
            ->orderBy('classes.numeric_level')
            ->orderBy('sections.name')
            ->get();

        $section = null;
        if ($sectionId = $request->integer('section_id')) {
            $section = $sections->firstWhere('id', $sectionId);
        }
        $section ??= $sections->first();

        $periods = Period::query()->ordered()->get();

        $entries = collect();
        if ($section) {
            $entries = TimetableEntry::query()
                ->where('section_id', $section->id)
                ->with(['subject', 'teacher'])
                ->get()
                ->keyBy(fn (TimetableEntry $entry) => $entry->period_id.'-'.$entry->day_of_week);
        }

        return view('timetable.index', [
            'sections' => $sections,
            'section' => $section,
            'periods' => $periods,
            'entries' => $entries,
            'displayDays' => self::DISPLAY_DAYS,
        ]);
    }

    public function create(Request $request): View
    {
        $section = Section::findOrFail($request->integer('section_id'));
        $period = Period::findOrFail($request->integer('period_id'));
        $dayOfWeek = $request->integer('day_of_week');

        return view('timetable.create', [
            'section' => $section,
            'period' => $period,
            'dayOfWeek' => $dayOfWeek,
            'subjects' => Subject::query()->where('class_id', $section->class_id)->active()->orderBy('name')->get(),
            'teachers' => Teacher::query()->active()->orderBy('first_name')->get(),
        ]);
    }

    public function store(StoreTimetableEntryRequest $request): RedirectResponse
    {
        $entry = TimetableEntry::create($request->validated());

        AuditLogger::log('create', 'timetable_entries', $entry->id, null, $entry->only(['section_id', 'period_id', 'day_of_week', 'subject_id', 'teacher_id']));

        return redirect()->route('timetable.index', ['section_id' => $entry->section_id])->with('status', __('app.timetable_entry_created'));
    }

    public function edit(TimetableEntry $timetable_entry): View
    {
        $timetable_entry->load(['section', 'period']);

        return view('timetable.edit', [
            'entry' => $timetable_entry,
            'subjects' => Subject::query()->where('class_id', $timetable_entry->section->class_id)->active()->orderBy('name')->get(),
            'teachers' => Teacher::query()->active()->orderBy('first_name')->get(),
        ]);
    }

    public function update(UpdateTimetableEntryRequest $request, TimetableEntry $timetable_entry): RedirectResponse
    {
        $original = $timetable_entry->only(['subject_id', 'teacher_id']);

        $timetable_entry->update($request->validated());

        AuditLogger::log('update', 'timetable_entries', $timetable_entry->id, $original, $timetable_entry->only(['subject_id', 'teacher_id']));

        return redirect()->route('timetable.index', ['section_id' => $timetable_entry->section_id])->with('status', __('app.timetable_entry_updated'));
    }

    public function destroy(TimetableEntry $timetable_entry): RedirectResponse
    {
        $sectionId = $timetable_entry->section_id;

        $timetable_entry->delete();

        AuditLogger::log('delete', 'timetable_entries', $timetable_entry->id);

        return redirect()->route('timetable.index', ['section_id' => $sectionId])->with('status', __('app.timetable_entry_deleted'));
    }
}
