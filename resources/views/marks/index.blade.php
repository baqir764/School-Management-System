<x-layouts.app :title="__('app.marks_enter')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">{{ $examSubject->subject->name }}</h1>
            <div class="text-muted small">
                {{ $examSubject->exam->name }} &middot; {{ $examSubject->exam->classRoom->name }} &middot;
                {{ __('app.field_full_marks') }}: {{ $examSubject->full_marks }} &middot;
                {{ __('app.field_pass_marks') }}: {{ $examSubject->pass_marks }}
            </div>
        </div>
        <a href="{{ route('exams.show', $examSubject->exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
    </div>

    <div class="card">
        <form method="POST" action="{{ route('marks.store', $examSubject) }}">
            @csrf
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('app.field_roll_number') }}</th>
                            <th>{{ __('app.field_student_name') }}</th>
                            <th>{{ __('app.field_marks_obtained') }}</th>
                            <th>{{ __('app.field_absent') }}</th>
                            <th>{{ __('app.field_remarks') }}</th>
                            <th>{{ __('app.field_grade') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($students as $student)
                            @php
                                $mark = $marks->get($student->id);
                                $grade = $mark ? \App\Models\GradeScale::forPercentage($mark->percentage()) : null;
                            @endphp
                            <tr>
                                <td>{{ $student->enrollments->first()?->roll_number ?? '—' }}</td>
                                <td>{{ $student->fullName() }}</td>
                                <td>
                                    @can('marks.enter')
                                        <input type="number" step="0.01" min="0" max="{{ $examSubject->full_marks }}" name="marks[{{ $student->id }}]" value="{{ old("marks.{$student->id}", $mark?->marks_obtained) }}" class="form-control form-control-sm @error("marks.{$student->id}") is-invalid @enderror" style="min-width:100px">
                                        @error("marks.{$student->id}")<div class="text-danger small">{{ $message }}</div>@enderror
                                    @else
                                        {{ $mark?->is_absent ? '—' : ($mark?->marks_obtained ?? '—') }}
                                    @endcan
                                </td>
                                <td class="text-center">
                                    @can('marks.enter')
                                        <input type="checkbox" class="form-check-input" name="absent[{{ $student->id }}]" value="1" @checked(old("absent.{$student->id}", $mark?->is_absent))>
                                    @else
                                        @if ($mark?->is_absent)
                                            <span class="badge text-bg-secondary">{{ __('app.attendance_status_absent') }}</span>
                                        @endif
                                    @endcan
                                </td>
                                <td>
                                    @can('marks.enter')
                                        <input type="text" name="remarks[{{ $student->id }}]" value="{{ old("remarks.{$student->id}", $mark?->remarks) }}" class="form-control form-control-sm" style="min-width:160px">
                                    @else
                                        {{ $mark?->remarks ?? '—' }}
                                    @endcan
                                </td>
                                <td>
                                    @if ($grade)
                                        <span class="badge text-bg-info">{{ $grade->grade_name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
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

            @can('marks.enter')
                @if ($students->isNotEmpty())
                    <div class="card-footer bg-white">
                        <button type="submit" class="btn btn-primary">{{ __('app.marks_save') }}</button>
                    </div>
                @endif
            @endcan
        </form>
    </div>
</x-layouts.app>
