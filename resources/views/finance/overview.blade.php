@php $currency = \App\Models\Setting::current()->currency; @endphp
<x-layouts.app :title="__('app.nav_finance_overview')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_finance_overview') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('salary-payments.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-wallet2 me-1"></i>{{ __('app.nav_payroll') }}
            </a>
            <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-cash-stack me-1"></i>{{ __('app.nav_expenses') }}
            </a>
            <a href="{{ route('misc-incomes.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-cash-coin me-1"></i>{{ __('app.nav_misc_incomes') }}
            </a>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('finance.overview') }}" class="row g-2 align-items-end">
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
            </form>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card border-success">
                <div class="card-body">
                    <div class="text-muted small">{{ __('app.field_total_income') }}</div>
                    <div class="h4 mb-0 text-success">{{ number_format($totalIncome, 2) }} {{ $currency }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="text-muted small">{{ __('app.field_total_expense') }}</div>
                    <div class="h4 mb-0 text-danger">{{ number_format($totalExpense, 2) }} {{ $currency }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card {{ $netBalance >= 0 ? 'border-primary' : 'border-danger' }}">
                <div class="card-body">
                    <div class="text-muted small">{{ __('app.field_net_balance') }}</div>
                    <div class="h4 mb-0 {{ $netBalance >= 0 ? 'text-primary' : 'text-danger' }}">{{ number_format($netBalance, 2) }} {{ $currency }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body">
                    <div class="text-muted small">{{ __('app.field_total_income') }} — {{ __('app.nav_fee_invoices') }} / {{ __('app.nav_misc_incomes') }}</div>
                    <div class="h6 mb-0">{{ number_format($totalFeeIncome, 2) }} {{ $currency }} + {{ number_format($totalMiscIncome, 2) }} {{ $currency }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="text-muted small">{{ __('app.field_total_expense') }} — {{ __('app.nav_payroll') }} / {{ __('app.nav_expenses') }}</div>
                    <div class="h6 mb-0">{{ number_format($totalSalaryExpense, 2) }} {{ $currency }} + {{ number_format($totalOtherExpense, 2) }} {{ $currency }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-success">
                <div class="card-body">
                    <div class="text-muted small">{{ __('app.field_salary_discounts') }}</div>
                    <div class="h5 mb-1 text-success">+ {{ number_format($totalSalaryDiscount, 2) }} {{ $currency }}</div>
                    <div class="text-muted small">{{ __('app.field_salary_discounts_hint') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-danger">
                <div class="card-body">
                    <div class="text-muted small">{{ __('app.field_fee_discounts') }}</div>
                    <div class="h5 mb-1 text-danger">− {{ number_format($totalFeeDiscount, 2) }} {{ $currency }}</div>
                    <div class="text-muted small">{{ __('app.field_fee_discounts_hint') }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-semibold">{{ __('app.field_total_income') }} — {{ __('app.nav_fee_invoices') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_student') }}</th>
                                <th>{{ __('app.field_amount') }}</th>
                                <th>{{ __('app.field_payment_date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentFeePayments as $payment)
                                <tr>
                                    <td>{{ $payment->invoice?->student?->fullName() ?? '—' }}</td>
                                    <td>{{ number_format($payment->amount, 2) }} {{ $currency }}</td>
                                    <td>{{ \App\Support\JalaliDate::display($payment->payment_date) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-semibold">{{ __('app.field_total_expense') }} — {{ __('app.nav_payroll') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.column_name') }}</th>
                                <th>{{ __('app.field_amount') }}</th>
                                <th>{{ __('app.field_payment_date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentSalaryPayments as $payment)
                                <tr>
                                    <td>{{ $payment->payee?->fullName() ?? '—' }}</td>
                                    <td>{{ number_format($payment->netAmount(), 2) }} {{ $currency }}</td>
                                    <td>{{ \App\Support\JalaliDate::display($payment->payment_date) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mt-1">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-semibold">{{ __('app.field_total_income') }} — {{ __('app.nav_misc_incomes') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_income_category') }}</th>
                                <th>{{ __('app.field_amount') }}</th>
                                <th>{{ __('app.field_received_date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentMiscIncomes as $income)
                                <tr>
                                    <td>{{ $income->category?->name ?? '—' }}</td>
                                    <td>{{ number_format($income->amount, 2) }} {{ $currency }}</td>
                                    <td>{{ \App\Support\JalaliDate::display($income->received_date) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-semibold">{{ __('app.field_total_expense') }} — {{ __('app.nav_expenses') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_expense_category') }}</th>
                                <th>{{ __('app.field_amount') }}</th>
                                <th>{{ __('app.field_expense_date') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentExpenses as $expense)
                                <tr>
                                    <td>{{ $expense->category?->name ?? '—' }}</td>
                                    <td>{{ number_format($expense->amount, 2) }} {{ $currency }}</td>
                                    <td>{{ \App\Support\JalaliDate::display($expense->expense_date) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
