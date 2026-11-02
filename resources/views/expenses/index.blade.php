<x-layouts.app :title="__('app.nav_expenses')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_expenses') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('finance.overview') }}" class="btn btn-outline-secondary"><i class="bi bi-bar-chart-line me-1"></i>{{ __('app.nav_finance_overview') }}</a>
            @can('finance.manage')
                <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary"><i class="bi bi-tags me-1"></i>{{ __('app.nav_expense_categories') }}</a>
            @endcan
            @can('finance.record')
                <a href="{{ route('expenses.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.record_expense') }}</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('expenses.index') }}" class="row g-2 align-items-end">
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
                    <label class="form-label mb-1">{{ __('app.field_expense_category') }}</label>
                    <select name="expense_category_id" class="form-select" onchange="this.form.submit()">
                        <option value="">{{ __('app.all_categories') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
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
                        <th>{{ __('app.field_voucher_number') }}</th>
                        <th>{{ __('app.field_expense_category') }}</th>
                        <th>{{ __('app.field_title') }}</th>
                        <th>{{ __('app.field_amount') }}</th>
                        <th>{{ __('app.field_expense_date') }}</th>
                        <th>{{ __('app.field_paid_by') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currency = \App\Models\Setting::current()->currency; @endphp
                    @forelse ($expenses as $expense)
                        <tr>
                            <td>{{ $expense->voucher_number }}</td>
                            <td>{{ $expense->category?->name ?? '—' }}</td>
                            <td>{{ $expense->title }}</td>
                            <td>{{ number_format($expense->amount, 2) }} {{ $currency }}</td>
                            <td>{{ \App\Support\JalaliDate::display($expense->expense_date) }}</td>
                            <td>{{ $expense->paidBy?->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('expenses.voucher', $expense) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.expense_voucher_title') }}"><i class="bi bi-receipt"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($expenses->hasPages())
            <div class="card-footer bg-white">{{ $expenses->links() }}</div>
        @endif
    </div>
</x-layouts.app>
