@php
    $user = auth()->user();
@endphp
<aside class="sms-sidebar" id="smsSidebar">
    <div class="sms-sidebar-brand d-flex align-items-center gap-2 px-3 pt-3 pb-2">
        @if ($logo = \App\Models\Setting::current()->logo_path)
            <img src="{{ Storage::url($logo) }}" alt="" style="height:32px;width:32px;object-fit:cover" class="rounded">
        @else
            <div class="rounded bg-primary d-flex align-items-center justify-content-center" style="height:32px;width:32px">
                <i class="bi bi-mortarboard text-white"></i>
            </div>
        @endif
        <span class="fw-semibold text-white text-truncate">{{ \App\Models\Setting::current()->school_name }}</span>
    </div>

    <nav class="sms-sidebar-nav nav flex-column px-3 pb-2">
        <div class="sms-sidebar-nav-inner d-flex flex-column flex-nowrap">
        <div class="nav-section-title">{{ __('app.nav_general') }}</div>
        <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
            <i class="bi bi-speedometer2 me-2"></i>{{ __('app.nav_dashboard') }}
        </a>

        @canany(['academics.view', 'teachers.view', 'students.view', 'parents.view'])
            <div class="nav-section-title">{{ __('app.nav_academics_section') }}</div>
        @endcanany

        @can('academics.view')
            @php $structureOpen = request()->routeIs('academic-years.*', 'shifts.*', 'classes.*', 'sections.*', 'subjects.*'); @endphp
            <button type="button" class="nav-submenu-toggle {{ $structureOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#structureSubmenu" aria-expanded="{{ $structureOpen ? 'true' : 'false' }}" aria-controls="structureSubmenu">
                <span><i class="bi bi-layers me-2"></i>{{ __('app.nav_group_structure') }}</span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="collapse {{ $structureOpen ? 'show' : '' }}" id="structureSubmenu">
                <div class="ms-3 d-flex flex-column gap-1 py-1">
                    <a href="{{ route('academic-years.index') }}" class="nav-link {{ request()->routeIs('academic-years.*') ? 'active' : '' }}">
                        <i class="bi bi-calendar-range me-2"></i>{{ __('app.nav_academic_years') }}
                    </a>
                    <a href="{{ route('shifts.index') }}" class="nav-link {{ request()->routeIs('shifts.*') ? 'active' : '' }}">
                        <i class="bi bi-sunrise me-2"></i>{{ __('app.nav_shifts') }}
                    </a>
                    <a href="{{ route('classes.index') }}" class="nav-link {{ request()->routeIs('classes.*') ? 'active' : '' }}">
                        <i class="bi bi-diagram-3 me-2"></i>{{ __('app.nav_classes') }}
                    </a>
                    <a href="{{ route('sections.index') }}" class="nav-link {{ request()->routeIs('sections.*') ? 'active' : '' }}">
                        <i class="bi bi-grid-3x3-gap me-2"></i>{{ __('app.nav_sections') }}
                    </a>
                    <a href="{{ route('subjects.index') }}" class="nav-link {{ request()->routeIs('subjects.*') ? 'active' : '' }}">
                        <i class="bi bi-journal-bookmark me-2"></i>{{ __('app.nav_subjects') }}
                    </a>
                </div>
            </div>
        @endcan

        @can('teachers.view')
            <a href="{{ route('teachers.index') }}" class="nav-link {{ request()->routeIs('teachers.*') ? 'active' : '' }}">
                <i class="bi bi-person-workspace me-2"></i>{{ __('app.nav_teachers') }}
            </a>
        @endcan

        @can('students.view')
            <a href="{{ route('students.index') }}" class="nav-link {{ request()->routeIs('students.*') ? 'active' : '' }}">
                <i class="bi bi-mortarboard me-2"></i>{{ __('app.nav_students') }}
            </a>
        @endcan

        @can('parents.view')
            <a href="{{ route('parents.index') }}" class="nav-link {{ request()->routeIs('parents.*') ? 'active' : '' }}">
                <i class="bi bi-people me-2"></i>{{ __('app.nav_parents') }}
            </a>
        @endcan

        @canany(['timetable.view', 'timetable.manage', 'attendance.view', 'attendance.mark', 'exams.view'])
            <div class="nav-section-title">{{ __('app.nav_operations_section') }}</div>
        @endcanany

        @canany(['timetable.view', 'timetable.manage'])
            @php $timetableOpen = request()->routeIs('timetable.*', 'timetable-entries.*', 'periods.*'); @endphp
            <button type="button" class="nav-submenu-toggle {{ $timetableOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#timetableSubmenu" aria-expanded="{{ $timetableOpen ? 'true' : 'false' }}" aria-controls="timetableSubmenu">
                <span><i class="bi bi-calendar2-week me-2"></i>{{ __('app.nav_timetable') }}</span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="collapse {{ $timetableOpen ? 'show' : '' }}" id="timetableSubmenu">
                <div class="ms-3 d-flex flex-column gap-1 py-1">
                    @can('timetable.view')
                        <a href="{{ route('timetable.index') }}" class="nav-link {{ request()->routeIs('timetable.*') || request()->routeIs('timetable-entries.*') ? 'active' : '' }}">
                            <i class="bi bi-calendar-week me-2"></i>{{ __('app.nav_timetable_grid') }}
                        </a>
                    @endcan
                    @can('timetable.manage')
                        <a href="{{ route('periods.index') }}" class="nav-link {{ request()->routeIs('periods.*') ? 'active' : '' }}">
                            <i class="bi bi-clock me-2"></i>{{ __('app.nav_periods') }}
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        @canany(['attendance.view', 'attendance.mark'])
            @php $attendanceOpen = request()->routeIs('attendance.*', 'staff-attendance.*'); @endphp
            <button type="button" class="nav-submenu-toggle {{ $attendanceOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#attendanceSubmenu" aria-expanded="{{ $attendanceOpen ? 'true' : 'false' }}" aria-controls="attendanceSubmenu">
                <span><i class="bi bi-clipboard-check me-2"></i>{{ __('app.nav_attendance') }}</span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="collapse {{ $attendanceOpen ? 'show' : '' }}" id="attendanceSubmenu">
                <div class="ms-3 d-flex flex-column gap-1 py-1">
                    @can('attendance.view')
                        <a href="{{ route('attendance.index') }}" class="nav-link {{ request()->routeIs('attendance.index') ? 'active' : '' }}">
                            <i class="bi bi-calendar-check me-2"></i>{{ __('app.nav_attendance_daily') }}
                        </a>
                        <a href="{{ route('attendance.history') }}" class="nav-link {{ request()->routeIs('attendance.history', 'attendance.export') ? 'active' : '' }}">
                            <i class="bi bi-clock-history me-2"></i>{{ __('app.attendance_history') }}
                        </a>
                    @endcan
                    @can('attendance.mark')
                        <a href="{{ route('attendance.scan') }}" class="nav-link {{ request()->routeIs('attendance.scan*') ? 'active' : '' }}">
                            <i class="bi bi-upc-scan me-2"></i>{{ __('app.nav_attendance_scan') }}
                        </a>
                        <a href="{{ route('staff-attendance.mark') }}" class="nav-link {{ request()->routeIs('staff-attendance.*') ? 'active' : '' }}">
                            <i class="bi bi-person-badge me-2"></i>{{ __('app.nav_staff_attendance') }}
                        </a>
                    @elsecan('attendance.view')
                        <a href="{{ route('staff-attendance.index') }}" class="nav-link {{ request()->routeIs('staff-attendance.*') ? 'active' : '' }}">
                            <i class="bi bi-person-badge me-2"></i>{{ __('app.nav_staff_attendance') }}
                        </a>
                    @endcan
                </div>
            </div>
        @endcanany

        @can('exams.view')
            @php $examsOpen = request()->routeIs('exams.*', 'grade-scales.*', 'marks.*'); @endphp
            <button type="button" class="nav-submenu-toggle {{ $examsOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#examsSubmenu" aria-expanded="{{ $examsOpen ? 'true' : 'false' }}" aria-controls="examsSubmenu">
                <span><i class="bi bi-patch-check me-2"></i>{{ __('app.nav_group_exams') }}</span>
                <i class="bi bi-chevron-down"></i>
            </button>
            <div class="collapse {{ $examsOpen ? 'show' : '' }}" id="examsSubmenu">
                <div class="ms-3 d-flex flex-column gap-1 py-1">
                    <a href="{{ route('exams.index') }}" class="nav-link {{ request()->routeIs('exams.*', 'marks.*') ? 'active' : '' }}">
                        <i class="bi bi-file-earmark-text me-2"></i>{{ __('app.nav_exams') }}
                    </a>
                    <a href="{{ route('grade-scales.index') }}" class="nav-link {{ request()->routeIs('grade-scales.*') ? 'active' : '' }}">
                        <i class="bi bi-award me-2"></i>{{ __('app.nav_grade_scales') }}
                    </a>
                </div>
            </div>
        @endcan

        @canany(['fees.view', 'payroll.view', 'finance.view', 'finance.manage'])
            <div class="nav-section-title">{{ __('app.nav_finance_section') }}</div>
            @can('fees.view')
                @php $feesOpen = request()->routeIs('fee-structures.*', 'fee-invoices.*'); @endphp
                <button type="button" class="nav-submenu-toggle {{ $feesOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#feesSubmenu" aria-expanded="{{ $feesOpen ? 'true' : 'false' }}" aria-controls="feesSubmenu">
                    <span><i class="bi bi-cash-stack me-2"></i>{{ __('app.nav_group_fees') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse {{ $feesOpen ? 'show' : '' }}" id="feesSubmenu">
                    <div class="ms-3 d-flex flex-column gap-1 py-1">
                        <a href="{{ route('fee-structures.index') }}" class="nav-link {{ request()->routeIs('fee-structures.*') ? 'active' : '' }}">
                            <i class="bi bi-card-list me-2"></i>{{ __('app.nav_fee_structure') }}
                        </a>
                        <a href="{{ route('fee-invoices.index') }}" class="nav-link {{ request()->routeIs('fee-invoices.*') && ! request()->routeIs('fee-invoices.dues') ? 'active' : '' }}">
                            <i class="bi bi-receipt me-2"></i>{{ __('app.nav_fee_invoices') }}
                        </a>
                        <a href="{{ route('fee-invoices.dues') }}" class="nav-link {{ request()->routeIs('fee-invoices.dues') ? 'active' : '' }}">
                            <i class="bi bi-exclamation-triangle me-2"></i>{{ __('app.nav_fee_dues') }}
                        </a>
                    </div>
                </div>
            @endcan
            @can('payroll.view')
                @php $payrollOpen = request()->routeIs('employees.*', 'salary-payments.*'); @endphp
                <button type="button" class="nav-submenu-toggle {{ $payrollOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#payrollSubmenu" aria-expanded="{{ $payrollOpen ? 'true' : 'false' }}" aria-controls="payrollSubmenu">
                    <span><i class="bi bi-briefcase me-2"></i>{{ __('app.nav_group_payroll') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse {{ $payrollOpen ? 'show' : '' }}" id="payrollSubmenu">
                    <div class="ms-3 d-flex flex-column gap-1 py-1">
                        <a href="{{ route('employees.index') }}" class="nav-link {{ request()->routeIs('employees.*') ? 'active' : '' }}">
                            <i class="bi bi-person-badge me-2"></i>{{ __('app.nav_employees') }}
                        </a>
                        <a href="{{ route('salary-payments.index') }}" class="nav-link {{ request()->routeIs('salary-payments.*') ? 'active' : '' }}">
                            <i class="bi bi-wallet2 me-2"></i>{{ __('app.nav_payroll') }}
                        </a>
                    </div>
                </div>
            @endcan
            @canany(['finance.view', 'finance.manage'])
                @php $financeRecordsOpen = request()->routeIs('expenses.*', 'misc-incomes.*', 'expense-categories.*', 'income-categories.*'); @endphp
                <button type="button" class="nav-submenu-toggle {{ $financeRecordsOpen ? 'active' : '' }}" data-bs-toggle="collapse" data-bs-target="#financeRecordsSubmenu" aria-expanded="{{ $financeRecordsOpen ? 'true' : 'false' }}" aria-controls="financeRecordsSubmenu">
                    <span><i class="bi bi-cash me-2"></i>{{ __('app.nav_group_finance_records') }}</span>
                    <i class="bi bi-chevron-down"></i>
                </button>
                <div class="collapse {{ $financeRecordsOpen ? 'show' : '' }}" id="financeRecordsSubmenu">
                    <div class="ms-3 d-flex flex-column gap-1 py-1">
                        @can('finance.view')
                            <a href="{{ route('expenses.index') }}" class="nav-link {{ request()->routeIs('expenses.*') ? 'active' : '' }}">
                                <i class="bi bi-cash-stack me-2"></i>{{ __('app.nav_expenses') }}
                            </a>
                            <a href="{{ route('misc-incomes.index') }}" class="nav-link {{ request()->routeIs('misc-incomes.*') ? 'active' : '' }}">
                                <i class="bi bi-cash-coin me-2"></i>{{ __('app.nav_misc_incomes') }}
                            </a>
                        @endcan
                        @can('finance.manage')
                            <a href="{{ route('expense-categories.index') }}" class="nav-link {{ request()->routeIs('expense-categories.*') ? 'active' : '' }}">
                                <i class="bi bi-tags me-2"></i>{{ __('app.nav_expense_categories') }}
                            </a>
                            <a href="{{ route('income-categories.index') }}" class="nav-link {{ request()->routeIs('income-categories.*') ? 'active' : '' }}">
                                <i class="bi bi-tags me-2"></i>{{ __('app.nav_income_categories') }}
                            </a>
                        @endcan
                    </div>
                </div>
            @endcanany
            @canany(['fees.view', 'payroll.view', 'finance.view'])
                <a href="{{ route('finance.overview') }}" class="nav-link {{ request()->routeIs('finance.*') ? 'active' : '' }}">
                    <i class="bi bi-bar-chart-line me-2"></i>{{ __('app.nav_finance_overview') }}
                </a>
            @endcanany
        @endcanany

        @canany(['users.manage', 'announcements.view', 'settings.manage'])
            <div class="nav-section-title">{{ __('app.nav_administration') }}</div>
            @can('users.manage')
                <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}">
                    <i class="bi bi-people me-2"></i>{{ __('app.nav_users') }}
                </a>
            @endcan
            @can('announcements.view')
                <a href="{{ route('announcements.index') }}" class="nav-link {{ request()->routeIs('announcements.*') ? 'active' : '' }}">
                    <i class="bi bi-megaphone me-2"></i>{{ __('app.nav_announcements') }}
                </a>
            @endcan
            @can('settings.manage')
                <a href="{{ route('settings.edit') }}" class="nav-link {{ request()->routeIs('settings.*') ? 'active' : '' }}">
                    <i class="bi bi-gear me-2"></i>{{ __('app.nav_school_settings') }}
                </a>
            @endcan
        @endcanany
        </div>
    </nav>

    <div class="sms-sidebar-foot text-secondary small px-3 py-3 opacity-75">
        {{ __('app.nav_more_modules_soon') }}
    </div>
</aside>
