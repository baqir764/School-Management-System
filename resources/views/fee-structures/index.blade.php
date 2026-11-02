<x-layouts.app :title="__('app.nav_fee_structure')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_fee_structure') }}</h1>
        @can('fees.manage')
            <a href="{{ route('fee-structures.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_fee_structure') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('fee-structures.index') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_academic_year') }}</label>
                    <select name="academic_year_id" class="form-select" onchange="this.form.submit()">
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($selectedAcademicYearId === $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_fee_name') }}</th>
                        <th>{{ __('app.field_class_name') }}</th>
                        <th>{{ __('app.field_amount') }}</th>
                        <th>{{ __('app.field_frequency') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($feeStructures as $feeStructure)
                        <tr>
                            <td>{{ $feeStructure->name }}</td>
                            <td>{{ $feeStructure->classRoom->name ?? __('app.all_classes') }}</td>
                            <td>{{ number_format($feeStructure->amount, 2) }} {{ \App\Models\Setting::current()->currency }}</td>
                            <td>{{ __('app.frequency_'.$feeStructure->frequency) }}</td>
                            <td>
                                @if ($feeStructure->is_active)
                                    <span class="badge text-bg-success">{{ __('app.status_active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('app.status_inactive') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('fees.manage')
                                        <a href="{{ route('fee-invoices.create', $feeStructure) }}" class="btn btn-outline-secondary" title="{{ __('app.generate_invoices') }}"><i class="bi bi-receipt-cutoff"></i></a>
                                        <a href="{{ route('fee-structures.edit', $feeStructure) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('fee-structures.destroy', $feeStructure) }}" data-confirm="{{ __('app.confirm_delete_fee_structure') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($feeStructures->hasPages())
            <div class="card-footer bg-white">{{ $feeStructures->links() }}</div>
        @endif
    </div>
</x-layouts.app>
