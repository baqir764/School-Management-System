<?php

namespace App\Http\Controllers\Fees;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\StoreFeeStructureRequest;
use App\Http\Requests\Fees\UpdateFeeStructureRequest;
use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\FeeStructure;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeeStructureController extends Controller
{
    public function index(Request $request): View
    {
        $academicYearId = $request->integer('academic_year_id') ?: AcademicYear::current()?->id;

        return view('fee-structures.index', [
            'feeStructures' => FeeStructure::query()
                ->with(['academicYear', 'classRoom'])
                ->when($academicYearId, fn ($q) => $q->where('academic_year_id', $academicYearId))
                ->orderBy('class_id')
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'selectedAcademicYearId' => $academicYearId,
        ]);
    }

    public function create(): View
    {
        return view('fee-structures.create', $this->formData());
    }

    public function store(StoreFeeStructureRequest $request): RedirectResponse
    {
        $feeStructure = FeeStructure::create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::log('create', 'fee_structures', $feeStructure->id, null, $feeStructure->only(['academic_year_id', 'class_id', 'name', 'amount']));

        return redirect()->route('fee-structures.index')->with('status', __('app.fee_structure_created'));
    }

    public function edit(FeeStructure $fee_structure): View
    {
        return view('fee-structures.edit', array_merge($this->formData(), ['feeStructure' => $fee_structure]));
    }

    public function update(UpdateFeeStructureRequest $request, FeeStructure $fee_structure): RedirectResponse
    {
        $original = $fee_structure->only(['academic_year_id', 'class_id', 'name', 'amount', 'is_active']);

        $fee_structure->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active'),
        ]);

        AuditLogger::log('update', 'fee_structures', $fee_structure->id, $original, $fee_structure->only(['academic_year_id', 'class_id', 'name', 'amount', 'is_active']));

        return redirect()->route('fee-structures.index')->with('status', __('app.fee_structure_updated'));
    }

    public function destroy(FeeStructure $fee_structure): RedirectResponse
    {
        $fee_structure->delete();

        AuditLogger::log('delete', 'fee_structures', $fee_structure->id);

        return redirect()->route('fee-structures.index')->with('status', __('app.fee_structure_deleted'));
    }

    private function formData(): array
    {
        return [
            'academicYears' => AcademicYear::query()->orderByDesc('start_date')->get(),
            'classes' => ClassRoom::query()->active()->ordered()->get(),
        ];
    }
}
