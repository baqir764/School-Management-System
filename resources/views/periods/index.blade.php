<x-layouts.app :title="__('app.nav_periods')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('app.nav_periods') }}</h1>
        @can('timetable.manage')
            <a href="{{ route('periods.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_period') }}</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_period_name') }}</th>
                        <th>{{ __('app.column_time_range') }}</th>
                        <th>{{ __('app.field_sort_order') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($periods as $period)
                        <tr>
                            <td>{{ $period->name }}</td>
                            <td>{{ $period->timeRangeLabel() }}</td>
                            <td>{{ $period->sort_order }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('timetable.manage')
                                        <a href="{{ route('periods.edit', $period) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('periods.destroy', $period) }}" data-confirm="{{ __('app.confirm_delete_period') }}">
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
        @if ($periods->hasPages())
            <div class="card-footer bg-white">{{ $periods->links() }}</div>
        @endif
    </div>
</x-layouts.app>
