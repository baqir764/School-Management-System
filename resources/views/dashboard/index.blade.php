@php
    // Teacher dashboard: hide financial cards + admin widgets only for Teacher
    // (pure Teacher role). Super/School Administrator keep full dashboard
    // even if they also hold Teacher role.
    $isTeacherDashboard = auth()->user()->hasRole('Teacher')
        && ! auth()->user()->hasAnyRole(['Super Administrator', 'School Administrator']);
    // Pure Parent dashboard: a parent with no staff/admin role sees only
    // their own children's overview - never school-wide figures (total
    // users, fee income, ...) that belong to management.
    $isPureParent = auth()->user()->hasRole('Parent')
        && ! auth()->user()->hasAnyRole(['Super Administrator', 'School Administrator', 'Principal', 'Teacher', 'Accountant', 'Librarian', 'Storekeeper', 'Receptionist']);
@endphp
<x-layouts.app :title="__('app.nav_dashboard')">
    @if (! empty($parentOverview))
        @include('dashboard._parent', ['parentOverview' => $parentOverview])
    @endif

    @unless($isPureParent)
    <div class="row g-3 mb-4">
        <x-stat-card :label="__('app.stat_total_students')" :value="$cards['total_students']" icon="bi-mortarboard" color="primary" />
        @unless($isTeacherDashboard)
            <x-stat-card :label="__('app.stat_total_teachers')" :value="$cards['total_teachers']" icon="bi-person-workspace" color="success" />
            <x-stat-card :label="__('app.stat_total_staff')" :value="$cards['total_staff']" icon="bi-people" color="info" />
            <x-stat-card :label="__('app.stat_total_classes')" :value="$cards['total_classes']" icon="bi-diagram-3" color="warning" />
        @endunless

        <x-stat-card :label="__('app.stat_present_today')" :value="$cards['present_today']" icon="bi-check-circle" color="success" />
        <x-stat-card :label="__('app.stat_absent_today')" :value="$cards['absent_today']" icon="bi-x-circle" color="danger" />
        <x-stat-card :label="__('app.stat_late_today')" :value="$cards['late_today']" icon="bi-clock-history" color="warning" />
        <x-stat-card :label="__('app.stat_upcoming_exams')" :value="$cards['upcoming_exams']" icon="bi-journal-check" color="primary" />

        @unless($isTeacherDashboard)
            <x-stat-card :label="__('app.stat_pending_fees')" :value="number_format($cards['pending_fees_amount']).' '.\App\Models\Setting::current()->currency" icon="bi-cash-coin" color="danger" />
            <x-stat-card :label="__('app.stat_today_payments')" :value="number_format($cards['today_payments_amount']).' '.\App\Models\Setting::current()->currency" icon="bi-wallet2" color="success" />
            <x-stat-card :label="__('app.stat_active_users')" :value="$cards['total_active_users']" icon="bi-person-check" color="info" />
        @endunless
    </div>
    @endunless

    @unless($isTeacherDashboard || $isPureParent)
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">{{ __('app.chart_users_by_role') }}</div>
                <div class="card-body">
                    @if (count($usersByRole))
                        <canvas id="usersByRoleChart" height="220"></canvas>
                    @else
                        <p class="text-muted mb-0">{{ __('app.no_data_yet') }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header bg-white fw-semibold">{{ __('app.recent_activity') }}</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.column_time') }}</th>
                                <th>{{ __('app.column_user') }}</th>
                                <th>{{ __('app.column_action') }}</th>
                                <th>{{ __('app.column_module') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($recentActivity as $entry)
                                <tr>
                                    <td class="text-muted small">{{ $entry->created_at?->diffForHumans() }}</td>
                                    <td>{{ $entry->user_name }}</td>
                                    <td><span class="badge text-bg-light border">{{ $entry->action }}</span></td>
                                    <td>{{ $entry->module }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    @endunless

    @if (count($usersByRole) && ! $isTeacherDashboard && ! $isPureParent)
        @push('scripts')
        @endpush
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const ctx = document.getElementById('usersByRoleChart');
                if (ctx && window.Chart) {
                    new window.Chart(ctx, {
                        type: 'doughnut',
                        data: {
                            labels: @json(array_keys($usersByRole)),
                            datasets: [{
                                data: @json(array_values($usersByRole)),
                                backgroundColor: ['#0d6efd', '#198754', '#0dcaf0', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#fd7e14', '#6c757d', '#d63384'],
                            }],
                        },
                        options: { plugins: { legend: { position: 'bottom' } } },
                    });
                }
            });
        </script>
    @endif
</x-layouts.app>
