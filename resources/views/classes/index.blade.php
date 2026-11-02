<x-layouts.app :title="__('app.nav_classes')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('app.nav_classes') }}</h1>
        @can('academics.manage')
            <a href="{{ route('classes.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_class') }}</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_numeric_level') }}</th>
                        <th>{{ __('app.field_class_name') }}</th>
                        <th>{{ __('app.column_sections') }}</th>
                        <th>{{ __('app.column_subjects') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($classes as $class)
                        <tr>
                            <td>{{ $class->numeric_level }}</td>
                            <td><a href="{{ route('classes.show', $class) }}">{{ $class->name }}</a></td>
                            <td>{{ $class->sections_count }}</td>
                            <td>{{ $class->subjects_count }}</td>
                            <td>
                                @if ($class->is_active)
                                    <span class="badge text-bg-success">{{ __('app.status_active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('app.status_disabled') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('classes.show', $class) }}" class="btn btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                                    @can('academics.manage')
                                        <a href="{{ route('classes.edit', $class) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('classes.destroy', $class) }}" data-confirm="{{ __('app.confirm_delete_class') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                        @if ($class->sections_count + $class->enrollments_count + $class->fee_structures_count + $class->exams_count > 0)
                                            <form method="POST" action="{{ route('classes.force-destroy', $class) }}" data-confirm="{{ __('app.confirm_force_delete_class') }}" class="d-inline">
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
                            <td colspan="6" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($classes->hasPages())
            <div class="card-footer bg-white">{{ $classes->links() }}</div>
        @endif
    </div>
</x-layouts.app>
