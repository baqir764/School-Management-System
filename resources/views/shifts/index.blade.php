<x-layouts.app :title="__('app.nav_shifts')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('app.nav_shifts') }}</h1>
        @can('academics.manage')
            <a href="{{ route('shifts.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_shift') }}</a>
        @endcan
    </div>

    <p class="text-muted small">{{ __('app.field_shift_help') }}</p>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_shift_name') }}</th>
                        <th>{{ __('app.field_start_time') }}</th>
                        <th>{{ __('app.column_classes') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($shifts as $shift)
                        <tr>
                            <td>{{ $shift->name }}</td>
                            <td>{{ $shift->startTimeLabel() }}</td>
                            <td>{{ $shift->classes_count }}</td>
                            <td>
                                <span class="badge text-bg-{{ $shift->is_active ? 'success' : 'secondary' }}">
                                    {{ $shift->is_active ? __('app.status_active') : __('app.status_inactive') }}
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('academics.manage')
                                        <a href="{{ route('shifts.edit', $shift) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('shifts.destroy', $shift) }}" data-confirm="{{ __('app.confirm_delete_shift') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                        @if ($shift->classes_count + $shift->teachers_count + $shift->employees_count > 0)
                                            <form method="POST" action="{{ route('shifts.force-destroy', $shift) }}" data-confirm="{{ __('app.confirm_force_delete_shift') }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger" title="{{ __('app.force_delete') }}"><i class="bi bi-trash-fill"></i></button>
                                            </form>
                                        @endif
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
        @if ($shifts->hasPages())
            <div class="card-footer bg-white">{{ $shifts->links() }}</div>
        @endif
    </div>
</x-layouts.app>
