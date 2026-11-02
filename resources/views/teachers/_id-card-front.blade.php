<div class="id-card id-card-front">
    <div class="id-card-header">
        @if ($school->logo_path)
            <img src="{{ Storage::url($school->logo_path) }}" alt="" class="id-card-logo">
        @else
            <span class="id-card-logo-placeholder"><i class="bi bi-mortarboard"></i></span>
        @endif
        <div class="id-card-school-name">{{ $school->school_name }}</div>
    </div>
    <div class="id-card-title">{{ __('app.students_id_card') }}</div>
    <div class="id-card-body">
        <div class="id-card-photo">
            @if ($teacher->photo_path)
                <img src="{{ Storage::url($teacher->photo_path) }}" alt="">
            @else
                <div class="id-card-photo-placeholder"><i class="bi bi-person"></i></div>
            @endif
        </div>
        <div class="id-card-fields">
            <div class="id-card-name">{{ $teacher->fullName() }}</div>
            <div><span>{{ __('app.field_employee_code') }}:</span> {{ $teacher->employee_code }}</div>
            <div><span>{{ __('app.field_specialization') }}:</span> {{ $teacher->specialization ?? '—' }}</div>
            <div><span>{{ __('app.field_phone') }}:</span> {{ $teacher->phone ?? '—' }}</div>
        </div>
    </div>
    <div class="id-card-footer">
        <span>{{ __('app.id_card_academic_year') }}: {{ $academicYear->name ?? '—' }}</span>
        @if ($academicYear?->end_date)
            <span>{{ __('app.id_card_valid_thru') }}: {{ \App\Support\JalaliDate::display($academicYear->end_date) }}</span>
        @endif
    </div>
</div>
