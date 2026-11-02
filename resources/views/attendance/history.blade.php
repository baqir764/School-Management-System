@php
    $statusVariant = [
        'present' => 'success',
        'absent' => 'danger',
        'late' => 'warning',
        'excused' => 'secondary',
    ];
@endphp
<x-layouts.app :title="__('app.attendance_history')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.attendance_history') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('attendance.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('app.attendance_export') }}</a>
            @can('attendance.mark')
                <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-check me-1"></i>{{ __('app.nav_attendance') }}</a>
            @endcan
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.history') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_select_section') }}</label>
                    <select name="section_id" class="form-select">
                        @forelse ($sections as $option)
                            <option value="{{ $option->id }}" @selected($section && $section->id === $option->id)>{{ $option->classRoom->name }} — {{ $option->name }}</option>
                        @empty
                            <option value="">{{ __('app.no_data_yet') }}</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_student') }}</label>
                    <select name="student_id" class="form-select">
                        <option value="">{{ __('app.all_students') }}</option>
                        @foreach ($students as $student)
                            <option value="{{ $student->id }}" @selected($studentId === $student->id)>{{ $student->fullName() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_from_date') }}</label>
                    <x-jalali-date name="from" :value="$from" />
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_to_date') }}</label>
                    <x-jalali-date name="to" :value="$to" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0]" />
                </div>
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_status') }}</label>
                    <select name="status" class="form-select">
                        <option value="">{{ __('app.all_statuses') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status }}" @selected($statusFilter === $status)>{{ __('app.attendance_status_'.$status) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-outline-secondary">{{ __('app.filter') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if (! $section)
        <div class="card"><div class="card-body text-center text-muted py-5">{{ __('app.attendance_no_section_selected') }}</div></div>
    @else
        <div class="d-flex gap-2 flex-wrap mb-3">
            @foreach ($statuses as $status)
                <span class="badge text-bg-{{ $statusVariant[$status] }} fs-6 fw-normal">{{ __('app.attendance_status_'.$status) }}: {{ $statusCounts[$status] ?? 0 }}</span>
            @endforeach
        </div>
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('app.field_date') }}</th>
                            <th>{{ __('app.field_student_name') }}</th>
                            <th>{{ __('app.field_status') }}</th>
                            <th>{{ __('app.field_time_in') }}</th>
                            <th>{{ __('app.field_time_out') }}</th>
                            <th>{{ __('app.field_remarks') }}</th>
                            <th>{{ __('app.field_marked_by') }}</th>
                            @role('Super Administrator')
                                <th class="text-end">{{ __('app.column_actions') }}</th>
                            @endrole
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($records as $record)
                            <tr>
                                <td>{{ \App\Support\JalaliDate::display($record->date) }}</td>
                                <td>{{ $record->student->fullName() }}</td>
                                <td><span class="badge text-bg-{{ $statusVariant[$record->status] }}">{{ __('app.attendance_status_'.$record->status) }}</span></td>
                                <td>{{ $record->time_in ? substr($record->time_in, 0, 5) : '—' }}</td>
                                <td>{{ $record->time_out ? substr($record->time_out, 0, 5) : '—' }}</td>
                                <td>{{ $record->remarks ?? '—' }}</td>
                                <td>{{ $record->markedBy?->name ?? '—' }}</td>
                                @role('Super Administrator')
                                    <td class="text-end">
                                        <form method="POST" action="{{ route('attendance.destroy', $record) }}" data-confirm="{{ __('app.confirm_delete_attendance') }}" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger btn-sm" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                @endrole
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ auth()->user()->hasRole('Super Administrator') ? 8 : 7 }}" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($records->hasPages())
                <div class="card-footer bg-white">{{ $records->links() }}</div>
            @endif
        </div>
    @endif
</x-layouts.app>
