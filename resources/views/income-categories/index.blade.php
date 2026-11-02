<x-layouts.app :title="__('app.nav_income_categories')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_income_categories') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('misc-incomes.index') }}" class="btn btn-outline-secondary"><i class="bi bi-cash-coin me-1"></i>{{ __('app.nav_misc_incomes') }}</a>
            @can('finance.manage')
                <a href="{{ route('income-categories.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_income_category') }}</a>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_category_name') }}</th>
                        <th>{{ __('app.column_receipts') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($categories as $category)
                        <tr>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->misc_incomes_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $category->is_active ? 'success' : 'secondary' }}">
                                    {{ $category->is_active ? __('app.status_active') : __('app.status_inactive') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('finance.manage')
                                        <a href="{{ route('income-categories.edit', $category) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('income-categories.destroy', $category) }}" data-confirm="{{ __('app.confirm_delete_income_category') }}">
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
                            <td colspan="4" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($categories->hasPages())
            <div class="card-footer bg-white">{{ $categories->links() }}</div>
        @endif
    </div>
</x-layouts.app>
