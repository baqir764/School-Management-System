@php
    $statusVariant = [
        'present' => 'success',
        'absent' => 'danger',
        'late' => 'warning',
        'excused' => 'secondary',
    ];
@endphp
<x-layouts.app :title="__('app.nav_staff_attendance')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_staff_attendance') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('staff-attendance.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>{{ __('app.attendance_history') }}</a>
            <a href="{{ route('staff-attendance.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('app.attendance_export') }}</a>
            @can('attendance.mark')
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#importStaffModal"><i class="bi bi-upload me-1"></i>{{ __('app.attendance_import') }}</button>
            @endcan
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
            <form method="GET" action="{{ route('staff-attendance.mark') }}" class="row g-2 align-items-end" id="staffMarkDateForm">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_date') }}</label>
                    <x-jalali-date name="date" :value="$date" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0]" />
                </div>
            </form>
        </div>
    </div>

    <script>
        document.getElementById('staffMarkDateForm').addEventListener('change', function (event) {
            if (event.target.name && event.target.name.startsWith('date')) {
                event.target.form.submit();
            }
        });
    </script>

    <form method="POST" action="{{ route('staff-attendance.store-mark') }}">
        @csrf
        @php [$dateYear, $dateMonth, $dateDay] = \App\Support\JalaliDate::parts($date); @endphp
        <input type="hidden" name="date[year]" value="{{ $dateYear }}">
        <input type="hidden" name="date[month]" value="{{ $dateMonth }}">
        <input type="hidden" name="date[day]" value="{{ $dateDay }}">

        @foreach ([['teacher', $teachers], ['employee', $employees]] as [$type, $people])
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">
                    {{ $type === 'teacher' ? __('app.nav_teachers') : __('app.nav_employees') }} ({{ $people->count() }})
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0 js-staff-attendance-table">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_employee_code') }}</th>
                                <th>{{ __('app.column_name') }}</th>
                                <th>{{ __('app.column_detail') }}</th>
                                <th>{{ __('app.field_status') }}</th>
                                <th>{{ __('app.field_time_in') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($people as $person)
                                @php
                                    $key = $type.':'.$person->id;
                                    $record = $existing->get($key);
                                    // Default is ABSENT (not present): the clerk flips the
                                    // ones who showed up - same rule as the student roster.
                                    $currentStatus = old("statuses.{$key}", $record->status ?? 'absent');
                                    $detail = $type === 'teacher' ? ($person->specialization ?? '—') : ($person->position ?? '—');
                                @endphp
                                <tr>
                                    <td>{{ $person->employee_code }}</td>
                                    <td>{{ $person->fullName() }}</td>
                                    <td>{{ $detail }}</td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            @foreach ($statuses as $status)
                                                <input type="radio" class="btn-check" name="statuses[{{ $key }}]" id="status-{{ $type }}-{{ $person->id }}-{{ $status }}" value="{{ $status }}" autocomplete="off" @checked($currentStatus === $status)>
                                                <label class="btn btn-outline-{{ $statusVariant[$status] }}" for="status-{{ $type }}-{{ $person->id }}-{{ $status }}">{{ __('app.attendance_status_'.$status) }}</label>
                                            @endforeach
                                        </div>
                                        @error("statuses.{$key}")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </td>
                                    <td>
                                        <input type="time" name="time_in[{{ $key }}]" value="{{ old('time_in.'.$key, $record?->time_in ? substr($record->time_in, 0, 5) : '') }}" class="form-control form-control-sm js-time-in" data-row="{{ $key }}" data-start-time="{{ $startTimes->get($key) }}">
                                        @error("time_in.{$key}")<div class="text-danger small mt-1">{{ $message }}</div>@enderror
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
        @endforeach

        <button type="submit" class="btn btn-primary">{{ __('app.attendance_save') }}</button>
    </form>

    <script>
        // Typing (or clearing) a check-in time decides present vs late right
        // away, against that person's own shift start time (falls back to
        // the school-wide default) - each row carries its own start time
        // since, unlike the student roster, a teacher and an employee here
        // can each sit in a different shift. Mirrors the server-side rule
        // re-applied on save.
        document.querySelectorAll('.js-staff-attendance-table').forEach(function (table) {
            table.addEventListener('input', function (event) {
                if (! event.target.classList.contains('js-time-in')) {
                    return;
                }
                var startTime = event.target.dataset.startTime;
                var timeIn = event.target.value;
                if (! startTime || ! timeIn) {
                    return;
                }
                var status = timeIn > startTime ? 'late' : 'present';
                var radioId = 'status-' + event.target.dataset.row.replace(':', '-') + '-' + status;
                var radio = document.getElementById(radioId);
                if (radio) {
                    radio.checked = true;
                }
            });
        });
    </script>

    @can('attendance.mark')
        <div class="modal fade" id="importStaffModal" tabindex="-1" aria-labelledby="importStaffModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('staff-attendance.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="importStaffModalLabel">{{ __('app.attendance_import') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small"><code>Code, Name, Date, Status</code></p>
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
                    new bootstrap.Modal(document.getElementById('importStaffModal')).show();
                });
            </script>
        @enderror
    @endcan
</x-layouts.app>
