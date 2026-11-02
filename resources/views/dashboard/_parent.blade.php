@php
    $attendanceVariant = ['present' => 'success', 'absent' => 'danger', 'late' => 'warning', 'excused' => 'info'];
    $resultVariant = ['pass' => 'success', 'fail' => 'danger', 'incomplete' => 'secondary'];
@endphp
<div class="mb-4">
    <h2 class="h5 mb-3"><i class="bi bi-people me-2"></i>{{ __('app.parent_my_children') }}</h2>

    @foreach ($parentOverview as $child)
        <div class="card mb-3">
            <div class="card-header bg-white">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <strong>{{ $child['student']->fullName() }}</strong>
                    <span class="text-muted small">
                        {{ $child['student']->admission_number }}
                        @if ($child['class_label'])
                            &middot; {{ $child['class_label'] }}
                        @endif
                    </span>
                </div>
                @if ($child['homeroom_teacher'])
                    <div class="small text-muted mt-1">
                        {{ __('app.parent_homeroom_teacher') }}: {{ $child['homeroom_teacher']->fullName() }}
                    </div>
                @endif
            </div>
            <div class="card-body">
                @if (! $child['enrollment'])
                    <p class="text-muted mb-0">{{ __('app.parent_not_enrolled') }}</p>
                @else
                    <h3 class="h6">{{ __('app.parent_attendance_summary') }}</h3>
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        @foreach (['present', 'absent', 'late', 'excused'] as $status)
                            <span class="badge text-bg-{{ $attendanceVariant[$status] }} fs-6 fw-normal">
                                {{ __('app.attendance_status_'.$status) }}: {{ $child['attendance'][$status] }}
                            </span>
                        @endforeach
                    </div>

                    <h3 class="h6">{{ __('app.parent_recent_attendance') }}</h3>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('app.field_date') }}</th>
                                    <th>{{ __('app.field_status') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($child['attendance']['recent'] as $record)
                                    <tr>
                                        <td>{{ \App\Support\JalaliDate::display($record->date) }}</td>
                                        <td><span class="badge text-bg-{{ $attendanceVariant[$record->status] }}">{{ __('app.attendance_status_'.$record->status) }}</span></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="2" class="text-center text-muted py-3">{{ __('app.no_data_yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <h3 class="h6">{{ __('app.parent_exam_results') }}</h3>
                    @forelse ($child['exams'] as $examRow)
                        <div class="border rounded p-2 mb-2">
                            <div class="fw-semibold mb-2">{{ $examRow['exam']->name }}</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-2">
                                    <thead class="table-light">
                                        <tr>
                                            <th>{{ __('app.field_subject') }}</th>
                                            <th>{{ __('app.field_teacher') }}</th>
                                            <th class="text-center">{{ __('app.field_total') }}</th>
                                            <th class="text-center">{{ __('app.field_percentage') }}</th>
                                            <th class="text-center">{{ __('app.field_result') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($examRow['subjects'] as $subjectRow)
                                            <tr>
                                                <td>{{ $subjectRow['examSubject']->subject->name }}</td>
                                                <td>{{ $subjectRow['examSubject']->subject->teacher?->fullName() ?? '—' }}</td>
                                                <td class="text-center">
                                                    @if (! $subjectRow['mark'])
                                                        <span class="text-muted">—</span>
                                                    @elseif ($subjectRow['mark']->is_absent)
                                                        <span class="badge text-bg-secondary">{{ __('app.attendance_status_absent') }}</span>
                                                    @else
                                                        {{ $subjectRow['mark']->marks_obtained }}/{{ $subjectRow['examSubject']->full_marks }}
                                                    @endif
                                                </td>
                                                <td class="text-center">{{ $subjectRow['mark']?->percentage() !== null ? number_format($subjectRow['mark']->percentage(), 2).'%' : '—' }}</td>
                                                <td class="text-center">
                                                    @if (! $subjectRow['mark'] || $subjectRow['mark']->isPassing() === null)
                                                        <span class="text-muted">—</span>
                                                    @else
                                                        <span class="badge text-bg-{{ $subjectRow['mark']->isPassing() ? 'success' : 'danger' }}">{{ __('app.result_status_'.($subjectRow['mark']->isPassing() ? 'pass' : 'fail')) }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            <div class="small text-muted">
                                {{ __('app.field_total') }}: {{ $examRow['total_obtained'] }}/{{ $examRow['total_full'] }}
                                &middot; {{ __('app.field_percentage') }}: {{ $examRow['percentage'] !== null ? number_format($examRow['percentage'], 2).'%' : '—' }}
                                &middot; {{ __('app.field_grade') }}: {{ $examRow['grade'] ?? '—' }}
                                &middot; <span class="badge text-bg-{{ $resultVariant[$examRow['status']] }}">{{ __('app.result_status_'.$examRow['status']) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted">{{ __('app.parent_no_exams_yet') }}</p>
                    @endforelse

                    <h3 class="h6 mt-3">{{ __('app.parent_child_teachers') }}</h3>
                    @if ($child['teachers']->isEmpty())
                        <p class="text-muted mb-0">{{ __('app.no_data_yet') }}</p>
                    @else
                        <ul class="list-group list-group-flush">
                            @foreach ($child['teachers'] as $entry)
                                <li class="list-group-item px-0 d-flex justify-content-between align-items-center flex-wrap gap-1">
                                    <span>
                                        <i class="bi bi-person-workspace me-1"></i>{{ $entry['teacher']->fullName() }}
                                        @if ($entry['is_homeroom'])
                                            <span class="badge text-bg-primary ms-1">{{ __('app.parent_homeroom_teacher') }}</span>
                                        @endif
                                    </span>
                                    @if (! empty($entry['subjects']))
                                        <span class="text-muted small">{{ implode(', ', $entry['subjects']) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                @endif
            </div>
        </div>
    @endforeach
</div>
