<?php

namespace App\Http\Controllers\Academics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academics\StoreShiftRequest;
use App\Http\Requests\Academics\UpdateShiftRequest;
use App\Models\Shift;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Shifts (دوره): master data for a school running more than one daily
 * session (e.g. Grades 1-4 in the morning, Grades 5-6 in the afternoon).
 * Assigned to a Class (classes.shift_id) and directly to Teachers/
 * Employees (their own shift_id), then read by AttendanceController to
 * judge each person's "late" status against the right start time instead
 * of one school-wide clock. Lives under the same 'academics.manage' /
 * 'academics.view' permissions as classes/sections/subjects, since it's
 * academic structure in the same sense.
 */
class ShiftController extends Controller
{
    public function index(): View
    {
        return view('shifts.index', [
            'shifts' => Shift::query()
                ->withCount(['classes', 'teachers', 'employees'])
                ->ordered()
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('shifts.create');
    }

    public function store(StoreShiftRequest $request): RedirectResponse
    {
        $shift = Shift::create($request->validated());

        AuditLogger::log('create', 'shifts', $shift->id, null, $shift->only(['name', 'start_time']));

        return redirect()->route('shifts.index')->with('status', __('app.shift_created'));
    }

    public function edit(Shift $shift): View
    {
        return view('shifts.edit', ['shift' => $shift]);
    }

    public function update(UpdateShiftRequest $request, Shift $shift): RedirectResponse
    {
        $original = $shift->only(['name', 'start_time', 'is_active']);

        $shift->update($request->validated());

        AuditLogger::log('update', 'shifts', $shift->id, $original, $shift->only(['name', 'start_time', 'is_active']));

        return redirect()->route('shifts.index')->with('status', __('app.shift_updated'));
    }

    public function destroy(Shift $shift): RedirectResponse
    {
        abort_if(
            $shift->classes()->exists() || $shift->teachers()->exists() || $shift->employees()->exists(),
            403,
            __('app.cannot_delete_shift_in_use')
        );

        $shift->delete();

        AuditLogger::log('delete', 'shifts', $shift->id);

        return redirect()->route('shifts.index')->with('status', __('app.shift_deleted'));
    }

    /**
     * Force (permanent) delete: removes the shift even while classes,
     * teachers or employees are assigned to it. Those records are NOT
     * deleted - their shift_id is cleared first (belt-and-braces over the
     * nullOnDelete FKs) so they simply fall back to the school-wide start
     * time. The affected counts are kept in the audit log.
     */
    public function forceDestroy(Shift $shift): RedirectResponse
    {
        $shiftId = $shift->id;
        $affected = [
            'classes' => $shift->classes()->count(),
            'teachers' => $shift->teachers()->count(),
            'employees' => $shift->employees()->count(),
        ];

        DB::transaction(function () use ($shift) {
            $shift->classes()->update(['shift_id' => null]);
            $shift->teachers()->update(['shift_id' => null]);
            $shift->employees()->update(['shift_id' => null]);
            $shift->delete();
        });

        AuditLogger::log('force_delete', 'shifts', $shiftId, $affected);

        return redirect()->route('shifts.index')->with('status', __('app.shift_force_deleted'));
    }
}
