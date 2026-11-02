<x-layouts.app :title="$teacher->fullName()">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $teacher->fullName() }}</h1>
        <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
        <a href="{{ route('teachers.id-card', $teacher) }}" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="bi bi-person-vcard me-1"></i>{{ __('app.students_id_card') }}</a>
    </div>

    <div class="row g-4">
        <div class="col-md-4 text-center">
            @if ($teacher->photo_path)
                <img src="{{ Storage::url($teacher->photo_path) }}" alt="" class="img-thumbnail mb-3" style="max-height:180px">
            @else
                <div class="border rounded d-flex align-items-center justify-content-center text-muted mx-auto mb-3" style="height:180px;width:180px">
                    <i class="bi bi-person fs-1"></i>
                </div>
            @endif
            <p class="mb-1"><strong>{{ __('app.field_employee_code') }}:</strong> {{ $teacher->employee_code }}</p>
            <p class="mb-1"><strong>{{ __('app.field_status') }}:</strong> {{ __('app.status_'.$teacher->status) }}</p>
            <p class="mb-1"><strong>{{ __('app.field_phone') }}:</strong> {{ $teacher->phone ?? '—' }}</p>
            <p class="mb-1"><strong>{{ __('app.field_email') }}:</strong> {{ $teacher->email ?? '—' }}</p>
            @if ($teacher->monthly_salary)
                <p class="mb-1"><strong>{{ __('app.field_monthly_salary') }}:</strong> {{ number_format($teacher->monthly_salary, 2) }} {{ \App\Models\Setting::current()->currency }}</p>
            @endif
            @if ($teacher->user)
                <p class="mb-1"><strong>{{ __('app.field_link_user_account') }}:</strong> {{ $teacher->user->email }}</p>
            @endif
        </div>

        <div class="col-md-8">
            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">{{ __('app.nav_sections') }} ({{ __('app.field_class_teacher') }})</div>
                <ul class="list-group list-group-flush">
                    @forelse ($teacher->homeroomSections as $section)
                        <li class="list-group-item">{{ $section->classRoom->name }} - {{ $section->name }}</li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('app.no_data_yet') }}</li>
                    @endforelse
                </ul>
            </div>

            <div class="card mb-3">
                <div class="card-header bg-white fw-semibold">{{ __('app.nav_subjects') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($teacher->subjects as $subject)
                        <li class="list-group-item">{{ $subject->name }} @if($subject->classRoom) ({{ $subject->classRoom->name }}) @endif</li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('app.no_data_yet') }}</li>
                    @endforelse
                </ul>
            </div>

            @include('salary-payments._history', ['payee' => $teacher, 'payeeType' => 'teacher'])
        </div>
    </div>
</x-layouts.app>
