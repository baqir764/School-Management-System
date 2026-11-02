<x-layouts.app :title="$exam->name">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">{{ $exam->name }}</h1>
            <div class="text-muted small">
                {{ $exam->classRoom->name }} &middot; {{ $exam->academicYear->name }} &middot; {{ __('app.term_type_'.$exam->term_type) }}
                &middot; {{ \App\Support\JalaliDate::display($exam->start_date) }} – {{ \App\Support\JalaliDate::display($exam->end_date) }}
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('exams.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
            <a href="{{ route('exams.results', $exam) }}" class="btn btn-outline-primary btn-sm"><i class="bi bi-bar-chart me-1"></i>{{ __('app.exam_results') }}</a>
            @can('exams.manage')
                <a href="{{ route('exams.edit', $exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('app.edit') }}</a>
                <form method="POST" action="{{ route('exams.toggle-publish', $exam) }}" data-confirm="{{ $exam->is_published ? __('app.confirm_unpublish_exam') : __('app.confirm_publish_exam') }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-sm {{ $exam->is_published ? 'btn-outline-secondary' : 'btn-success' }}">
                        {{ $exam->is_published ? __('app.unpublish') : __('app.publish') }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div class="card">
        <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
            <span>{{ __('app.column_subjects') }}</span>
            @if ($exam->is_published)
                <span class="badge text-bg-success">{{ __('app.status_published') }}</span>
            @else
                <span class="badge text-bg-secondary">{{ __('app.status_unpublished') }}</span>
            @endif
        </div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_subject_name') }}</th>
                        <th>{{ __('app.field_exam_date') }}</th>
                        <th>{{ __('app.field_full_marks') }}</th>
                        <th>{{ __('app.field_pass_marks') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($exam->examSubjects as $examSubject)
                        <tr>
                            <td>{{ $examSubject->subject->name }}</td>
                            <td>{{ $examSubject->exam_date ? \App\Support\JalaliDate::display($examSubject->exam_date) : '—' }}</td>
                            <td>{{ $examSubject->full_marks }}</td>
                            <td>{{ $examSubject->pass_marks }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @can('marks.view')
                                        <a href="{{ route('marks.index', $examSubject) }}" class="btn btn-outline-primary" title="{{ __('app.marks_enter') }}"><i class="bi bi-pencil-square"></i></a>
                                    @endcan
                                    @can('exams.manage')
                                        <a href="{{ route('exam-subjects.edit', $examSubject) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('exam-subjects.destroy', $examSubject) }}" data-confirm="{{ __('app.confirm_remove_exam_subject') }}">
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

        @can('exams.manage')
            @if ($availableSubjects->isNotEmpty())
                <div class="card-footer bg-white">
                    <form method="POST" action="{{ route('exams.subjects.store', $exam) }}" class="row g-2 align-items-end" novalidate>
                        @csrf
                        <div class="col-auto">
                            <label class="form-label mb-1">{{ __('app.field_subject') }}</label>
                            <select name="subject_id" class="form-select form-select-sm @error('subject_id') is-invalid @enderror" required>
                                <option value="">{{ __('app.select_placeholder') }}</option>
                                @foreach ($availableSubjects as $subject)
                                    <option value="{{ $subject->id }}" @selected(old('subject_id') == $subject->id)>{{ $subject->name }}</option>
                                @endforeach
                            </select>
                            @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1">{{ __('app.field_exam_date') }}</label>
                            <x-jalali-date name="exam_date" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 1" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] + 1" />
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1">{{ __('app.field_full_marks') }}</label>
                            <input type="number" min="1" max="1000" name="full_marks" value="{{ old('full_marks', 100) }}" class="form-control form-control-sm @error('full_marks') is-invalid @enderror" required style="width:100px">
                            @error('full_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-auto">
                            <label class="form-label mb-1">{{ __('app.field_pass_marks') }}</label>
                            <input type="number" min="0" max="1000" name="pass_marks" value="{{ old('pass_marks', 40) }}" class="form-control form-control-sm @error('pass_marks') is-invalid @enderror" required style="width:100px">
                            @error('pass_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-primary btn-sm">{{ __('app.exam_subject_add') }}</button>
                        </div>
                    </form>
                </div>
            @endif
        @endcan
    </div>
</x-layouts.app>
