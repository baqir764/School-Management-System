<x-layouts.app :title="__('app.nav_fee_invoices')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_fee_invoices') }}</h1>
        <div class="d-flex gap-2">
            @can('fees.manage')
                <form method="POST" action="{{ route('fee-invoices.send-reminders') }}" data-confirm="{{ __('app.confirm_send_reminders') }}">
                    @csrf
                    @foreach (request()->query() as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="btn btn-outline-secondary">
                        <i class="bi bi-bell me-1"></i>{{ __('app.send_reminders') }}
                    </button>
                </form>
            @endcan
            <a href="{{ route('fee-invoices.print-many', request()->query()) }}" target="_blank" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i>{{ __('app.print_invoices') }}
            </a>
            <a href="{{ route('fee-invoices.dues') }}" class="btn btn-outline-secondary">
                <i class="bi bi-exclamation-triangle me-1"></i>{{ __('app.nav_fee_dues') }}
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('fee-invoices.index') }}" class="row g-2 align-items-end">
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
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.column_status') }}</label>
                    <select name="status" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('app.all_statuses') }}</option>
                        @foreach (['unpaid', 'partial', 'paid'] as $status)
                            <option value="{{ $status }}" @selected($selectedStatus === $status)>{{ __('app.status_'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_student') }}</label>
                    <input type="search" name="search" value="{{ $search }}" class="form-control" placeholder="{{ __('app.search_fee_invoices_placeholder') }}">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    @if ($search || $selectedStatus || $selectedClassId)
                        <a href="{{ route('fee-invoices.index', ['academic_year_id' => $selectedAcademicYearId]) }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
                    @endif
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
                        <th>{{ __('app.field_fee_head') }}</th>
                        <th>{{ __('app.field_amount') }}</th>
                        <th>{{ __('app.field_discount_amount') }}</th>
                        <th>{{ __('app.field_paid') }}</th>
                        <th>{{ __('app.field_balance') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        @php $currency = \App\Models\Setting::current()->currency; @endphp
                        <tr>
                            <td>{{ $invoice->student->fullName() }} <span class="text-muted small">({{ $invoice->student->admission_number }})</span></td>
                            <td>{{ $invoice->feeStructure->name }}</td>
                            <td>{{ number_format($invoice->amount, 2) }} {{ $currency }}</td>
                            <td>{{ $invoice->discount_amount > 0 ? number_format($invoice->discount_amount, 2).' '.$currency : '—' }}</td>
                            <td>{{ number_format($invoice->totalPaid(), 2) }} {{ $currency }}</td>
                            <td>{{ number_format($invoice->balance(), 2) }} {{ $currency }}</td>
                            <td>
                                @php $status = $invoice->status(); @endphp
                                <span class="badge {{ ['unpaid' => 'text-bg-danger', 'partial' => 'text-bg-warning', 'paid' => 'text-bg-success'][$status] }}">{{ __('app.status_'.$status) }}</span>
                            </td>
                            <td class="text-end">
                                @can('fees.manage')
                                    <a href="{{ route('fee-invoices.edit', $invoice) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.edit_discount') }}"><i class="bi bi-percent"></i></a>
                                @endcan
                                <a href="{{ route('fee-invoices.print', $invoice) }}" target="_blank" class="btn btn-outline-secondary btn-sm" title="{{ __('app.print_invoice_notice') }}"><i class="bi bi-printer"></i></a>
                                <a href="{{ route('fee-invoices.show', $invoice) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages())
            <div class="card-footer bg-white">{{ $invoices->links() }}</div>
        @endif
    </div>
</x-layouts.app>
