<?php

namespace App\Http\Controllers\Timetable;

use App\Http\Controllers\Controller;
use App\Http\Requests\Timetable\StorePeriodRequest;
use App\Http\Requests\Timetable\UpdatePeriodRequest;
use App\Models\Period;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PeriodController extends Controller
{
    public function index(): View
    {
        return view('periods.index', [
            'periods' => Period::query()->ordered()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('periods.create');
    }

    public function store(StorePeriodRequest $request): RedirectResponse
    {
        $period = Period::create($request->validated());

        AuditLogger::log('create', 'periods', $period->id, null, $period->only(['name', 'start_time', 'end_time', 'sort_order']));

        return redirect()->route('periods.index')->with('status', __('app.period_created'));
    }

    public function edit(Period $period): View
    {
        return view('periods.edit', ['period' => $period]);
    }

    public function update(UpdatePeriodRequest $request, Period $period): RedirectResponse
    {
        $original = $period->only(['name', 'start_time', 'end_time', 'sort_order']);

        $period->update($request->validated());

        AuditLogger::log('update', 'periods', $period->id, $original, $period->only(['name', 'start_time', 'end_time', 'sort_order']));

        return redirect()->route('periods.index')->with('status', __('app.period_updated'));
    }

    public function destroy(Period $period): RedirectResponse
    {
        abort_if($period->timetableEntries()->exists(), 403, __('app.cannot_delete_period_in_use'));

        $period->delete();

        AuditLogger::log('delete', 'periods', $period->id);

        return redirect()->route('periods.index')->with('status', __('app.period_deleted'));
    }
}
