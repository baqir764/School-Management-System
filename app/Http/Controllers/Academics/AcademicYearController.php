<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\StoreAcademicYearRequest;
use App\Http\Requests\Academics\UpdateAcademicYearRequest;
use App\Models\AcademicYear;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AcademicYearController extends Controller
{
    public function index(): View
    {
        return view('academic-years.index', [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('academic-years.create');
    }

    public function store(StoreAcademicYearRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['is_active', 'start_date', 'end_date']);
        $data['start_date'] = $request->jalaliDateFromInput('start_date');
        $data['end_date'] = $request->jalaliDateFromInput('end_date');

        $year = AcademicYear::create($data);

        if ($request->boolean('is_active')) {
            $year->activate();
        }

        AuditLogger::log('create', 'academic_years', $year->id, null, $year->only(['name', 'start_date', 'end_date', 'is_active']));

        return redirect()->route('academic-years.index')->with('status', __('app.academic_year_created'));
    }

    public function edit(AcademicYear $academicYear): View
    {
        return view('academic-years.edit', ['academicYear' => $academicYear]);
    }

    public function update(UpdateAcademicYearRequest $request, AcademicYear $academicYear): RedirectResponse
    {
        $original = $academicYear->only(['name', 'start_date', 'end_date', 'is_active']);

        $data = $request->safe()->except(['is_active', 'start_date', 'end_date']);
        $data['start_date'] = $request->jalaliDateFromInput('start_date');
        $data['end_date'] = $request->jalaliDateFromInput('end_date');

        $academicYear->update($data);

        if ($request->boolean('is_active') && ! $academicYear->is_active) {
            $academicYear->activate();
        }

        AuditLogger::log('update', 'academic_years', $academicYear->id, $original, $academicYear->only(['name', 'start_date', 'end_date', 'is_active']));

        return redirect()->route('academic-years.index')->with('status', __('app.academic_year_updated'));
    }

    public function activate(AcademicYear $academicYear): RedirectResponse
    {
        $academicYear->activate();

        AuditLogger::log('activate', 'academic_years', $academicYear->id);

        return back()->with('status', __('app.academic_year_activated'));
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        abort_if($academicYear->enrollments()->exists(), 403, __('app.cannot_delete_year_with_enrollments'));

        $academicYear->delete();

        AuditLogger::log('delete', 'academic_years', $academicYear->id);

        return redirect()->route('academic-years.index')->with('status', __('app.academic_year_deleted'));
    }
}
