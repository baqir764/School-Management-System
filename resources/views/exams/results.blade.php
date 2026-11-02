@php
    $statusVariant = ['pass' => 'success', 'fail' => 'danger', 'incomplete' => 'secondary'];
@endphp
<x-layouts.app :title="__('app.exam_results').' — '.$exam->name">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h1 class="h4 mb-1">{{ __('app.exam_results') }} — {{ $exam->name }}</h1>
            <div class="text-muted small">{{ $exam->classRoom->name }} &middot; {{ $exam->academicYear->name }}</div>
        </div>
        <a href="{{ route('exams.show', $exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0 text-center">
                <thead class="table-light">
                    <tr>
                        <th class="text-start">{{ __('app.field_student_name') }}</th>
                        @foreach ($exam->examSubjects as $examSubject)
                            <th>{{ $examSubject->subject->name }}<br><span class="small text-muted fw-normal">/{{ $examSubject->full_marks }}</span></th>
                        @endforeach
                        <th>{{ __('app.field_total') }}</th>
                        <th>{{ __('app.field_percentage') }}</th>
                        <th>{{ __('app.field_grade') }}</th>
                        <th>{{ __('app.field_result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $row)
                        <tr>
                            <td class="text-start">{{ $row['student']->fullName() }}</td>
                            @foreach ($row['subjects'] as $subjectRow)
                                <td>
                                    @if (! $subjectRow['mark'])
                                        <span class="text-muted">—</span>
                                    @elseif ($subjectRow['mark']->is_absent)
                                        <span class="badge text-bg-secondary">{{ __('app.attendance_status_absent') }}</span>
                                    @else
                                        {{ $subjectRow['mark']->marks_obtained ?? '—' }}
                                    @endif
                                </td>
                            @endforeach
                            <td>{{ $row['total_obtained'] }}/{{ $row['total_full'] }}</td>
                            <td>{{ $row['percentage'] !== null ? number_format($row['percentage'], 2).'%' : '—' }}</td>
                            <td>
                                @if ($row['grade'])
                                    <span class="badge text-bg-info">{{ $row['grade']->grade_name }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge text-bg-{{ $statusVariant[$row['status']] }}">{{ __('app.result_status_'.$row['status']) }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $exam->examSubjects->count() + 5 }}" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
