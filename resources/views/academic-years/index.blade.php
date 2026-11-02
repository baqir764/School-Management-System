<x-layouts.app :title="__('app.nav_academic_years')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('app.nav_academic_years') }}</h1>
        @can('academics.manage')
            <a href="{{ route('academic-years.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_academic_year') }}</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_year_name') }}</th>
                        <th>{{ __('app.field_start_date') }}</th>
                        <th>{{ __('app.field_end_date') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($academicYears as $year)
                        <tr>
                            <td>{{ $year->name }}</td>
                            <td>{{ \App\Support\JalaliDate::display($year->start_date) }}</td>
                            <td>{{ \App\Support\JalaliDate::display($year->end_date) }}</td>
                            <td>
                                @if ($year->is_active)
                                    <span class="badge text-bg-success">{{ __('app.status_active_year') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('app.status_inactive_year') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('academics.manage')
                                        @unless ($year->is_active)
                                            <form method="POST" action="{{ route('academic-years.activate', $year) }}" data-confirm="{{ __('app.confirm_activate_year') }}">
                                                @csrf
                                                <button class="btn btn-outline-success" title="{{ __('app.activate') }}"><i class="bi bi-check-circle"></i></button>
                                            </form>
                                        @endunless
                                        <a href="{{ route('academic-years.edit', $year) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('academic-years.destroy', $year) }}" data-confirm="{{ __('app.confirm_delete_year') }}">
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
                            <td colspan="5" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($academicYears->hasPages())
            <div class="card-footer bg-white">{{ $academicYears->links() }}</div>
        @endif
    </div>
</x-layouts.app>
