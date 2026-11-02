<x-layouts.app :title="__('app.nav_sections')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('app.nav_sections') }}</h1>
        @can('academics.manage')
            <a href="{{ route('sections.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_section') }}</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_class_name') }}</th>
                        <th>{{ __('app.field_section_name') }}</th>
                        <th>{{ __('app.field_class_teacher') }}</th>
                        <th>{{ __('app.field_capacity') }}</th>
                        <th>{{ __('app.column_enrolled') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($sections as $section)
                        <tr>
                            <td>{{ $section->classRoom->name }}</td>
                            <td>{{ $section->name }}</td>
                            <td>{{ $section->classTeacher?->fullName() ?? '—' }}</td>
                            <td>{{ $section->capacity ?? '—' }}</td>
                            <td>{{ $section->enrollments_count }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('academics.manage')
                                        <a href="{{ route('sections.edit', $section) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('sections.destroy', $section) }}" data-confirm="{{ __('app.confirm_delete_section') }}">
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
                            <td colspan="6" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($sections->hasPages())
            <div class="card-footer bg-white">{{ $sections->links() }}</div>
        @endif
    </div>
</x-layouts.app>
