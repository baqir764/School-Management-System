<x-layouts.app :title="__('app.nav_exams')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_exams') }}</h1>
        @can('exams.manage')
            <a href="{{ route('exams.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_exam') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('exams.index') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_academic_year') }}</label>
                    <select name="academic_year_id" class="form-select" onchange="this.form.submit()">
                        @foreach ($academicYears as $year)
                            <option value="{{ $year->id }}" @selected($selectedAcademicYearId === $year->id)>{{ $year->name }}</option>
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
                        <th>{{ __('app.field_exam_name') }}</th>
                        <th>{{ __('app.field_class_name') }}</th>
                        <th>{{ __('app.field_term_type') }}</th>
                        <th>{{ __('app.field_start_date') }}</th>
                        <th>{{ __('app.field_end_date') }}</th>
                        <th>{{ __('app.column_subjects') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exams as $exam)
                        <tr>
                            <td><a href="{{ route('exams.show', $exam) }}">{{ $exam->name }}</a></td>
                            <td>{{ $exam->classRoom->name }}</td>
                            <td>{{ __('app.term_type_'.$exam->term_type) }}</td>
                            <td>{{ \App\Support\JalaliDate::display($exam->start_date) }}</td>
                            <td>{{ \App\Support\JalaliDate::display($exam->end_date) }}</td>
                            <td>{{ $exam->exam_subjects_count }}</td>
                            <td>
                                @if ($exam->is_published)
                                    <span class="badge text-bg-success">{{ __('app.status_published') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('app.status_unpublished') }}</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('exams.show', $exam) }}" class="btn btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                                    @can('exams.manage')
                                        <a href="{{ route('exams.edit', $exam) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('exams.destroy', $exam) }}" data-confirm="{{ __('app.confirm_delete_exam') }}">
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
                            <td colspan="8" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($exams->hasPages())
            <div class="card-footer bg-white">{{ $exams->links() }}</div>
        @endif
    </div>
</x-layouts.app>
