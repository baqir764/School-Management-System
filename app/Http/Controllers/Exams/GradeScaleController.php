<?php

namespace App\Http\Controllers\Exams;

use App\Http\Controllers\Controller;
use App\Http\Requests\Exams\StoreGradeScaleRequest;
use App\Http\Requests\Exams\UpdateGradeScaleRequest;
use App\Models\GradeScale;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class GradeScaleController extends Controller
{
    public function index(): View
    {
        return view('grade-scales.index', [
            'gradeScales' => GradeScale::query()->ordered()->get(),
        ]);
    }

    public function create(): View
    {
        return view('grade-scales.create');
    }

    public function store(StoreGradeScaleRequest $request): RedirectResponse
    {
        $gradeScale = GradeScale::create($request->validated());

        AuditLogger::log('create', 'grade_scales', $gradeScale->id, null, $gradeScale->only(['grade_name', 'min_percentage', 'max_percentage']));

        return redirect()->route('grade-scales.index')->with('status', __('app.grade_scale_created'));
    }

    public function edit(GradeScale $grade_scale): View
    {
        return view('grade-scales.edit', ['gradeScale' => $grade_scale]);
    }

    public function update(UpdateGradeScaleRequest $request, GradeScale $grade_scale): RedirectResponse
    {
        $original = $grade_scale->only(['grade_name', 'min_percentage', 'max_percentage']);

        $grade_scale->update($request->validated());

        AuditLogger::log('update', 'grade_scales', $grade_scale->id, $original, $grade_scale->only(['grade_name', 'min_percentage', 'max_percentage']));

        return redirect()->route('grade-scales.index')->with('status', __('app.grade_scale_updated'));
    }

    public function destroy(GradeScale $grade_scale): RedirectResponse
    {
        $grade_scale->delete();

        AuditLogger::log('delete', 'grade_scales', $grade_scale->id);

        return redirect()->route('grade-scales.index')->with('status', __('app.grade_scale_deleted'));
    }
}
