<x-layouts.app :title="__('app.nav_grade_scales')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('app.nav_grade_scales') }}</h1>
        @can('exams.manage')
            <a href="{{ route('grade-scales.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_grade_scale') }}</a>
        @endcan
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_grade_name') }}</th>
                        <th>{{ __('app.field_min_percentage') }}</th>
                        <th>{{ __('app.field_max_percentage') }}</th>
                        <th>{{ __('app.field_description') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($gradeScales as $scale)
                        <tr>
                            <td class="fw-semibold">{{ $scale->grade_name }}</td>
                            <td>{{ number_format($scale->min_percentage, 2) }}%</td>
                            <td>{{ number_format($scale->max_percentage, 2) }}%</td>
                            <td>{{ $scale->description ?? '—' }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('exams.manage')
                                        <a href="{{ route('grade-scales.edit', $scale) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('grade-scales.destroy', $scale) }}" data-confirm="{{ __('app.confirm_delete_grade_scale') }}">
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
    </div>
</x-layouts.app>
