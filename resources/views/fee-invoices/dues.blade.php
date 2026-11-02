<x-layouts.app :title="__('app.dues_report_title')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.dues_report_title') }}</h1>
        <a href="{{ route('fee-invoices.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('app.nav_fee_invoices') }}
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('fee-invoices.dues') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_academic_year') }}</label>
                    <select name="academic_year_id" class="form-select" onchange="this.form.submit()">
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($selectedAcademicYearId === $year->id)>{{ $year->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_class_name') }}</label>
                    <select name="class_id" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('app.all_classes') }}</option>
                        @foreach ($classes as $class)
                            <option value="{{ $class->id }}" @selected($selectedClassId === $class->id)>{{ $class->name }}</option>
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
                        <th>{{ __('app.field_student') }}</th>
                        <th>{{ __('app.column_invoiced') }}</th>
                        <th>{{ __('app.field_discount_amount') }}</th>
                        <th>{{ __('app.column_paid') }}</th>
                        <th>{{ __('app.column_due') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currency = \App\Models\Setting::current()->currency; @endphp
                    @forelse ($rows as $row)
                        <tr>
                            <td>{{ $row['student']->fullName() }} <span class="text-muted small">({{ $row['student']->admission_number }})</span></td>
                            <td>{{ number_format($row['invoiced'], 2) }} {{ $currency }}</td>
                            <td>{{ $row['discount'] > 0 ? number_format($row['discount'], 2).' '.$currency : '—' }}</td>
                            <td>{{ number_format($row['paid'], 2) }} {{ $currency }}</td>
                            <td class="fw-semibold text-danger">{{ number_format($row['due'], 2) }} {{ $currency }}</td>
                            <td class="text-end">
                                @can('students.view')
                                    <a href="{{ route('students.show', $row['student']) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('app.no_dues') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
