@php
    $statusVariant = [
        'present' => 'success',
        'absent' => 'danger',
        'late' => 'warning',
        'excused' => 'secondary',
    ];
@endphp
<x-layouts.app :title="__('app.nav_attendance')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_attendance') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
            @can('attendance.mark')
                <a href="{{ route('attendance.scan') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-upc-scan me-1"></i>{{ __('app.attendance_scan') }}</a>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importAttendanceModal"><i class="bi bi-upload me-1"></i>{{ __('app.attendance_import') }}</button>
            @endcan
            @if ($section)
                @php
                    [$dYear, $dMonth, $dDay] = \App\Support\JalaliDate::parts($date);
                    $exportParams = [
                        'section_id' => $section->id,
                        'from' => ['year' => $dYear, 'month' => $dMonth, 'day' => $dDay],
                        'to' => ['year' => $dYear, 'month' => $dMonth, 'day' => $dDay],
                    ];
                @endphp
                <a href="{{ route('attendance.export', $exportParams) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('app.attendance_export') }}</a>
            @endif
            <a href="{{ route('attendance.history') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>{{ __('app.attendance_history') }}</a>
        </div>
    </div>

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <p class="fw-semibold mb-2">{{ __('app.attendance_import_errors_heading') }}</p>
            <ul class="mb-0">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('attendance.index') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_select_section') }}</label>
                    <select name="section_id" class="form-select" onchange="this.form.submit()">
                        @forelse ($sections as $option)
                            <option value="{{ $option->id }}" @selected($section && $section->id === $option->id)>{{ $option->classRoom->name }} — {{ $option->name }}</option>
                        @empty
                            <option value="">{{ __('app.no_data_yet') }}</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-auto" id="attendance-date-picker">
                    <label class="form-label mb-1">{{ __('app.field_date') }}</label>
                    <x-jalali-date name="date" :value="$date" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0]" />
                </div>
            </form>
        </div>
    </div>

    <script>
        // Auto-submits the filter form whenever any of the three
        // day/month/year dropdowns change - mirrors the single native
        // date input's old onchange="this.form.submit()" behavior.
        document.getElementById('attendance-date-picker').addEventListener('change', function (event) {
            event.target.form.submit();
        });
    </script>

    @if (! $academicYear)
        <div class="card"><div class="card-body text-center text-muted py-5">{{ __('app.no_active_academic_year') }}</div></div>
    @elseif (! $section)
        <div class="card"><div class="card-body text-center text-muted py-5">{{ __('app.attendance_no_section_selected') }}</div></div>
    @else
        <div class="card">
            <form method="POST" action="{{ route('attendance.store') }}">
                @csrf
                <input type="hidden" name="section_id" value="{{ $section->id }}">
                <input type="hidden" name="academic_year_id" value="{{ $academicYear->id }}">
                @php [$dateYear, $dateMonth, $dateDay] = \App\Support\JalaliDate::parts($date); @endphp
                <input type="hidden" name="date[year]" value="{{ $dateYear }}">
                <input type="hidden" name="date[month]" value="{{ $dateMonth }}">
                <input type="hidden" name="date[day]" value="{{ $dateDay }}">

                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="attendance-roster-table" data-expected-start-time="{{ $expectedStartTime }}">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_roll_number') }}</th>
                                <th>{{ __('app.field_student_name') }}</th>
                                <th>{{ __('app.field_status') }}</th>
                                <th>{{ __('app.field_time_in') }}</th>
                                <th>{{ __('app.field_time_out') }}</th>
                                <th>{{ __('app.field_remarks') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($students as $student)
                                @php
                                    $record = $existing->get($student->id);
                                    // Unmarked rows default to ABSENT (not present):
                                    // the clerk flips the ones who showed up.
                                    $currentStatus = old("statuses.{$student->id}", $record->status ?? 'absent');
                                @endphp
                                <tr>
                                    <td>{{ $student->currentEnrollment()?->roll_number ?? '—' }}</td>
                                    <td>{{ $student->fullName() }}</td>
                                    <td>
                                        @can('attendance.mark')
                                            <div class="btn-group btn-group-sm" role="group">
                                                @foreach ($statuses as $status)
                                                    <input type="radio" class="btn-check" name="statuses[{{ $student->id }}]" id="status-{{ $student->id }}-{{ $status }}" value="{{ $status }}" autocomplete="off" @checked($currentStatus === $status)>
                                                    <label class="btn btn-outline-{{ $statusVariant[$status] }}" for="status-{{ $student->id }}-{{ $status }}">{{ __('app.attendance_status_'.$status) }}</label>
                                                @endforeach
                                            </div>
                                            @error("statuses.{$student->id}")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                        @else
                                            <span class="badge text-bg-{{ $statusVariant[$record->status ?? 'absent'] }}">{{ __('app.attendance_status_'.($record->status ?? 'absent')) }}</span>
                                            @unless ($record)
                                                <span class="text-muted small ms-1">{{ __('app.attendance_not_marked') }}</span>
                                            @endunless
                                        @endcan
                                    </td>
                                    <td>
                                        @can('attendance.mark')
                                            <input type="time" name="time_in[{{ $student->id }}]" value="{{ old("time_in.{$student->id}", $record?->time_in ? substr($record->time_in, 0, 5) : '') }}" class="form-control form-control-sm js-time-in" data-row="{{ $student->id }}">
                                            @error("time_in.{$student->id}")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                        @else
                                            {{ $record?->time_in ? substr($record->time_in, 0, 5) : '—' }}
                                        @endcan
                                    </td>
                                    <td>
                                        @can('attendance.mark')
                                            <input type="time" name="time_out[{{ $student->id }}]" value="{{ old("time_out.{$student->id}", $record?->time_out ? substr($record->time_out, 0, 5) : '') }}" class="form-control form-control-sm">
                                            @error("time_out.{$student->id}")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                        @else
                                            {{ $record?->time_out ? substr($record->time_out, 0, 5) : '—' }}
                                        @endcan
                                    </td>
                                    <td>
                                        @can('attendance.mark')
                                            <input type="text" name="remarks[{{ $student->id }}]" value="{{ old("remarks.{$student->id}", $record->remarks ?? '') }}" class="form-control form-control-sm" style="min-width:160px">
                                        @else
                                            {{ $record->remarks ?? '—' }}
                                        @endcan
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

                @can('attendance.mark')
                    @if ($students->isNotEmpty())
                        <div class="card-footer bg-white">
                            <button type="submit" class="btn btn-primary">{{ __('app.attendance_save') }}</button>
                        </div>
                    @endif
                @endcan
            </form>
        </div>

        @can('attendance.mark')
            <script>
                // Typing (or clearing) a check-in time decides present vs
                // late right away, against this section's own shift start
                // time (falls back to the school-wide default, exactly the
                // same rule the server re-applies on save) - so the clerk
                // sees the real status immediately instead of only after
                // submitting the form.
                (function () {
                    var table = document.getElementById('attendance-roster-table');
                    if (! table) {
                        return;
                    }
                    var startTime = table.dataset.expectedStartTime || '';

                    table.addEventListener('input', function (event) {
                        if (! event.target.classList.contains('js-time-in') || ! startTime) {
                            return;
                        }
                        var row = event.target.dataset.row;
                        var timeIn = event.target.value;
                        if (! timeIn) {
                            return;
                        }
                        var status = timeIn > startTime ? 'late' : 'present';
                        var radio = document.getElementById('status-' + row + '-' + status);
                        if (radio) {
                            radio.checked = true;
                        }
                    });
                })();
            </script>
        @endcan
    @endif

    @can('attendance.mark')
        <div class="modal fade" id="importAttendanceModal" tabindex="-1" aria-labelledby="importAttendanceModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('attendance.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="importAttendanceModalLabel">{{ __('app.attendance_import') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">{{ __('app.attendance_import_hint') }}</p>
                            <input type="file" name="file" accept=".xlsx,.xls" class="form-control @error('file') is-invalid @enderror" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('app.attendance_import') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @error('file')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('importAttendanceModal')).show();
                });
            </script>
        @enderror
    @endcan
</x-layouts.app>
