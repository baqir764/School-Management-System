<x-layouts.app :title="$class->name">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $class->name }}</h1>
        <div class="d-flex gap-2">
            @can('academics.manage')
                <form method="POST" action="{{ route('classes.destroy', $class) }}" data-confirm="{{ __('app.confirm_delete_class') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>{{ __('app.delete') }}</button>
                </form>
                @if ($class->sections_count + $class->enrollments_count + $class->fee_structures_count + $class->exams_count > 0)
                    <form method="POST" action="{{ route('classes.force-destroy', $class) }}" data-confirm="{{ __('app.confirm_force_delete_class') }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-danger btn-sm"><i class="bi bi-trash-fill me-1"></i>{{ __('app.force_delete') }}</button>
                    </form>
                @endif
            @endcan
            <a href="{{ route('classes.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">{{ __('app.nav_sections') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_section_name') }}</th>
                                <th>{{ __('app.field_class_teacher') }}</th>
                                <th>{{ __('app.field_capacity') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($class->sections as $section)
                                <tr>
                                    <td>{{ $section->name }}</td>
                                    <td>{{ $section->classTeacher?->fullName() ?? '—' }}</td>
                                    <td>{{ $section->capacity ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-center text-muted py-3">{{ __('app.no_data_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">{{ __('app.nav_subjects') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_subject_name') }}</th>
                                <th>{{ __('app.field_subject_teacher') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($class->subjects as $subject)
                                <tr>
                                    <td>{{ $subject->name }}</td>
                                    <td>{{ $subject->teacher?->fullName() ?? '—' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="2" class="text-center text-muted py-3">{{ __('app.no_data_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
