<x-layouts.app :title="$employee->fullName()">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $employee->fullName() }}</h1>
        <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
        <a href="{{ route('employees.id-card', $employee) }}" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="bi bi-person-vcard me-1"></i>{{ __('app.students_id_card') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-md-4 text-center">
            <div class="border rounded d-flex align-items-center justify-content-center text-muted mx-auto mb-3" style="height:180px;width:180px">
                <i class="bi bi-person-badge fs-1"></i>
            </div>
            <p class="mb-1"><strong>{{ __('app.field_employee_code') }}:</strong> {{ $employee->employee_code }}</p>
            <p class="mb-1"><strong>{{ __('app.field_position') }}:</strong> {{ $employee->position }}</p>
            <p class="mb-1"><strong>{{ __('app.field_status') }}:</strong> {{ __('app.status_'.$employee->status) }}</p>
            <p class="mb-1"><strong>{{ __('app.field_phone') }}:</strong> {{ $employee->phone ?? '—' }}</p>
            <p class="mb-1"><strong>{{ __('app.field_monthly_salary') }}:</strong> {{ number_format($employee->monthly_salary, 2) }} {{ \App\Models\Setting::current()->currency }}</p>
            @if ($employee->joining_date)
                <p class="mb-1"><strong>{{ __('app.field_joining_date') }}:</strong> {{ \App\Support\JalaliDate::display($employee->joining_date) }}</p>
            @endif
        </div>

        <div class="col-md-8">
            @include('salary-payments._history', ['payee' => $employee, 'payeeType' => 'employee'])
        </div>
    </div>
</x-layouts.app>
