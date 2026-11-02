@php [$dateYear, $dateMonth, $dateDay] = \App\Support\JalaliDate::parts($date); @endphp
<x-layouts.app :title="__('app.attendance_scan')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.attendance_scan') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('attendance.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clipboard-check me-1"></i>{{ __('app.nav_attendance') }}</a>
            <a href="{{ route('attendance.history') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock-history me-1"></i>{{ __('app.attendance_history') }}</a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-body">
                    <form method="GET" action="{{ route('attendance.scan') }}" class="row g-2 align-items-end mb-3" id="scanDateForm">
                        <div class="col-auto">
                            <label class="form-label mb-1">{{ __('app.field_date') }}</label>
                            <x-jalali-date name="date" :value="$date" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0]" />
                        </div>
                    </form>

                    <script>
                        document.getElementById('scanDateForm').addEventListener('change', function (event) {
                            if (event.target.name === 'date') {
                                event.target.form.submit();
                            }
                        });
                    </script>

                    <div id="scanFeedback"></div>

                    <div class="text-center mb-3">
                        <button type="button" class="btn btn-outline-primary" id="toggleCameraBtn">
                            <i class="bi bi-camera-video me-1"></i><span id="toggleCameraLabel">{{ __('app.attendance_scan_camera_on') }}</span>
                        </button>
                    </div>

                    <div id="cameraWrap" class="d-none">
                        <div class="text-center">
                            <video id="qrVideo" class="img-fluid rounded border" style="max-height:260px;width:100%;object-fit:cover" muted playsinline></video>
                            <canvas id="qrCanvas" class="d-none"></canvas>
                        </div>
                        <p class="text-muted small text-center mt-2 mb-0">{{ __('app.attendance_scan_camera_hint') }}</p>
                    </div>

                    <hr>

                    <label for="scanCode" class="form-label fw-semibold">{{ __('app.attendance_scan_code_label') }}</label>
                    <div class="input-group">
                        <input type="text" id="scanCode" class="form-control" placeholder="{{ __('app.attendance_scan_code_placeholder') }}" autocomplete="off" autofocus>
                        <button type="button" class="btn btn-primary" id="scanSubmitBtn"><i class="bi bi-search me-1"></i>{{ __('app.attendance_scan_go') }}</button>
                    </div>
                    <p class="text-muted small mt-2 mb-0">{{ __('app.attendance_scan_manual_hint') }}</p>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="h6 mb-0"><i class="bi bi-list-check me-1"></i>{{ __('app.attendance_scan_today') }} — {{ \App\Support\JalaliDate::display($date) }} ({{ $todayRecords->count() }})</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:420px;overflow-y:auto">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('app.field_admission_number') }}</th>
                                    <th>{{ __('app.field_student_name') }}</th>
                                    <th>{{ __('app.field_section') }}</th>
                                </tr>
                            </thead>
                            <tbody id="scanDoneList">
                                @forelse ($todayRecords as $record)
                                    <tr>
                                        <td>{{ $record->student->admission_number }}</td>
                                        <td>{{ $record->student->fullName() }}</td>
                                        <td>{{ $record->section->classRoom->name }} — {{ $record->section->name }}</td>
                                    </tr>
                                @empty
                                    <tr id="scanEmptyRow">
                                        <td colspan="3" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header bg-white">
                    <h5 class="h6 mb-0"><i class="bi bi-person-badge me-1"></i>{{ __('app.attendance_scan_staff_today') }} — {{ \App\Support\JalaliDate::display($date) }} ({{ $todayStaff->count() }})</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height:300px;overflow-y:auto">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('app.field_employee_code') }}</th>
                                    <th>{{ __('app.column_name') }}</th>
                                    <th>{{ __('app.column_detail') }}</th>
                                </tr>
                            </thead>
                            <tbody id="scanStaffList">
                                @forelse ($todayStaff as $record)
                                    <tr>
                                        <td>{{ $record->attendee->employee_code }}</td>
                                        <td>{{ $record->attendee->fullName() }}</td>
                                        <td>{{ $record->attendee_type === 'teacher' ? ($record->attendee->specialization ?? '—') : ($record->attendee->position ?? '—') }}</td>
                                    </tr>
                                @empty
                                    <tr id="scanStaffEmptyRow">
                                        <td colspan="3" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="{{ asset('vendor/jsqr.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const feedback = document.getElementById('scanFeedback');
            const input = document.getElementById('scanCode');
            const submitBtn = document.getElementById('scanSubmitBtn');
            const cameraWrap = document.getElementById('cameraWrap');
            const video = document.getElementById('qrVideo');
            const canvas = document.getElementById('qrCanvas');
            const cameraBtn = document.getElementById('toggleCameraBtn');
            const cameraLabel = document.getElementById('toggleCameraLabel');

            function showMessage(html, ok) {
                feedback.innerHTML = '<div class="alert alert-' + (ok ? 'success' : 'danger') + ' py-2 mb-3">' + html + '</div>';
            }

            function showCodeResult(data) {
                if (data.ok) {
                    showMessage('<i class="bi bi-check-circle me-1"></i>' + data.message, true);
                } else {
                    showMessage('<i class="bi bi-x-circle me-1"></i>' + data.message, false);
                }
            }

            function submitCode(code) {
                const value = (code || input.value).trim();
                if (!value) {
                    return;
                }

                input.value = '';
                const params = new URLSearchParams(new FormData(document.getElementById('scanDateForm')));
                params.set('code', value);

                fetch('{{ route('attendance.scan.store') }}', {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: params.toString(),
                })
                    .then((res) => res.json())
                    .then((data) => {
                        showCodeResult(data);
                        if (data.ok) {
                            refreshTodayList();
                        }
                    })
                    .catch(() => showMessage('{{ __('app.error_generic') }}', false));
            }

            function refreshTodayList() {
                fetch('{{ route('attendance.scan') }}' + '?' + new URLSearchParams(new FormData(document.getElementById('scanDateForm'))).toString(), {
                    headers: { 'Accept': 'text/html' },
                })
                    .then((res) => res.text())
                    .then((html) => {
                        const doc = new DOMParser().parseFromString(html, 'text/html');
                        const row = document.querySelector('#scanDoneList');
                        if (row) {
                            const newTbody = doc.querySelector('#scanDoneList');
                            if (newTbody) {
                                row.innerHTML = newTbody.innerHTML;
                            }
                        }
                        const staffRow = document.querySelector('#scanStaffList');
                        if (staffRow) {
                            const newStaffTbody = doc.querySelector('#scanStaffList');
                            if (newStaffTbody) {
                                staffRow.innerHTML = newStaffTbody.innerHTML;
                            }
                        }
                    })
                    .catch(() => {});
            }

            submitBtn.addEventListener('click', () => submitCode());
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    submitCode();
                }
            });

            let stream = null;
            let rafId = null;
            let scanning = false;

            function stopCamera() {
                if (rafId) {
                    cancelAnimationFrame(rafId);
                    rafId = null;
                }
                scanning = false;
                if (stream) {
                    stream.getTracks().forEach((t) => t.stop());
                    stream = null;
                }
                cameraWrap.classList.add('d-none');
                cameraBtn.classList.remove('btn-outline-danger');
                cameraBtn.classList.add('btn-outline-primary');
                cameraLabel.textContent = '{{ __('app.attendance_scan_camera_on') }}';
            }

            function startCamera() {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    showMessage('{{ __('app.attendance_scan_no_camera') }}', false);
                    return;
                }

                navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
                    .then((s) => {
                        stream = s;
                        video.srcObject = s;
                        cameraWrap.classList.remove('d-none');
                        cameraBtn.classList.remove('btn-outline-primary');
                        cameraBtn.classList.add('btn-outline-danger');
                        cameraLabel.textContent = '{{ __('app.attendance_scan_camera_off') }}';
                        video.play();
                        scanning = true;
                        tick();
                    })
                    .catch(() => showMessage('{{ __('app.attendance_scan_camera_error') }}', false));
            }

            function tick() {
                if (!scanning || !stream) {
                    return;
                }

                if (video.readyState === video.HAVE_ENOUGH_DATA) {
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    const data = ctx.getImageData(0, 0, canvas.width, canvas.height);
                    const code = window.jsQR ? jsQR(data.data, data.width, data.height, { inversionAttempts: 'dontInvert' }) : null;
                    if (code && code.data) {
                        submitCode(code.data);
                    }
                }

                rafId = requestAnimationFrame(tick);
            }

            cameraBtn.addEventListener('click', () => {
                if (stream) {
                    stopCamera();
                } else {
                    startCamera();
                }
            });
        });
    </script>
</x-layouts.app>