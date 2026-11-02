<x-layouts.app :title="__('app.nav_payroll')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_payroll') }}</h1>
        <a href="{{ route('finance.overview') }}" class="btn btn-outline-secondary">
            <i class="bi bi-bar-chart-line me-1"></i>{{ __('app.nav_finance_overview') }}
        </a>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('salary-payments.index') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_month') }}</label>
                    <select name="month" class="form-select" onchange="this.form.submit()">
                        @foreach (\App\Support\JalaliDate::MONTH_NAMES as $num => $label)
                            <option value="{{ $num }}" @selected($month === $num)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_year') }}</label>
                    <select name="year" class="form-select" onchange="this.form.submit()">
                        @foreach ($years as $y)
                            <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_payee_type') }}</label>
                    <select name="payee_type" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('app.all_payee_types') }}</option>
                        <option value="teacher" @selected($payeeType === 'teacher')>{{ __('app.payee_type_teacher') }}</option>
                        <option value="employee" @selected($payeeType === 'employee')>{{ __('app.payee_type_employee') }}</option>
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
                        <th>{{ __('app.field_voucher_number') }}</th>
                        <th>{{ __('app.column_name') }}</th>
                        <th>{{ __('app.field_payee_type') }}</th>
                        <th>{{ __('app.field_salary_month') }}</th>
                        <th>{{ __('app.field_amount') }}</th>
                        <th>{{ __('app.field_discount_amount') }}</th>
                        <th>{{ __('app.field_net_amount') }}</th>
                        <th>{{ __('app.field_payment_date') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currency = \App\Models\Setting::current()->currency; @endphp
                    @forelse ($payments as $payment)
                        <tr>
                            <td>{{ $payment->voucher_number }}</td>
                            <td>{{ $payment->payee?->fullName() ?? '—' }}</td>
                            <td>{{ __('app.payee_type_'.$payment->payee_type) }}</td>
                            <td>{{ \App\Support\JalaliDate::monthYearDisplay($payment->salary_month) }}</td>
                            <td>{{ number_format($payment->amount, 2) }} {{ $currency }}</td>
                            <td>{{ $payment->discount_amount > 0 ? number_format($payment->discount_amount, 2).' '.$currency : '—' }}</td>
                            <td>{{ number_format($payment->netAmount(), 2) }} {{ $currency }}</td>
                            <td>{{ \App\Support\JalaliDate::display($payment->payment_date) }}</td>
                            <td class="text-end">
                                <a href="{{ route('salary-payments.receipt', $payment) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.receipt_title') }}"><i class="bi bi-receipt"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($payments->hasPages())
            <div class="card-footer bg-white">{{ $payments->links() }}</div>
        @endif
    </div>
</x-layouts.app>
