<x-layouts.app :title="__('app.nav_misc_incomes')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_misc_incomes') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('finance.overview') }}" class="btn btn-outline-secondary"><i class="bi bi-bar-chart-line me-1"></i>{{ __('app.nav_finance_overview') }}</a>
            @can('finance.manage')
                <a href="{{ route('income-categories.index') }}" class="btn btn-outline-secondary"><i class="bi bi-tags me-1"></i>{{ __('app.nav_income_categories') }}</a>
            @endcan
            @can('finance.record')
                <a href="{{ route('misc-incomes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.record_misc_income') }}</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('misc-incomes.index') }}" class="row g-2 align-items-end">
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
                    <label class="form-label mb-1">{{ __('app.field_income_category') }}</label>
                    <select name="income_category_id" class="form-select" onchange="this.form.submit()">
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
                        <th>{{ __('app.field_receipt_number') }}</th>
                        <th>{{ __('app.field_income_category') }}</th>
                        <th>{{ __('app.field_title') }}</th>
                        <th>{{ __('app.field_amount') }}</th>
                        <th>{{ __('app.field_received_date') }}</th>
                        <th>{{ __('app.field_received_by') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $currency = \App\Models\Setting::current()->currency; @endphp
                    @forelse ($incomes as $income)
                        <tr>
                            <td>{{ $income->receipt_number }}</td>
                            <td>{{ $income->category?->name ?? '—' }}</td>
                            <td>{{ $income->title }}</td>
                            <td>{{ number_format($income->amount, 2) }} {{ $currency }}</td>
                            <td>{{ \App\Support\JalaliDate::display($income->received_date) }}</td>
                            <td>{{ $income->receivedBy?->name ?? '—' }}</td>
                            <td class="text-end">
                                <a href="{{ route('misc-incomes.receipt', $income) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.misc_income_receipt_title') }}"><i class="bi bi-receipt"></i></a>
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
        @if ($incomes->hasPages())
            <div class="card-footer bg-white">{{ $incomes->links() }}</div>
        @endif
    </div>
</x-layouts.app>
