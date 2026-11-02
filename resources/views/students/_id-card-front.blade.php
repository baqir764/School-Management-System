@php
    $enrollment = $student->enrollments->first();
    $className = $enrollment?->classRoom?->name;
    $sectionName = $enrollment?->section?->name;
@endphp
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
            @if ($student->photo_path)
                <img src="{{ Storage::url($student->photo_path) }}" alt="">
            @else
                <div class="id-card-photo-placeholder"><i class="bi bi-person"></i></div>
            @endif
        </div>
        <div class="id-card-fields">
            <div class="id-card-name">{{ $student->fullName() }}</div>
            <div><span>{{ __('app.field_admission_number') }}:</span> {{ $student->admission_number }}</div>
            <div><span>{{ __('app.column_current_class') }}:</span> {{ $className ? $className.($sectionName ? ' - '.$sectionName : '') : '—' }}</div>
            <div><span>{{ __('app.field_father_name') }}:</span> {{ $student->father_name ?? '—' }}</div>
            <div><span>{{ __('app.field_date_of_birth') }}:</span> {{ $student->date_of_birth ? \App\Support\JalaliDate::display($student->date_of_birth) : '—' }}</div>
            <div><span>{{ __('app.field_blood_group') }}:</span> {{ $student->blood_group ?? '—' }}</div>
        </div>
    </div>
    <div class="id-card-footer">
        <span>{{ __('app.id_card_academic_year') }}: {{ $academicYear->name ?? '—' }}</span>
        @if ($academicYear?->end_date)
            <span>{{ __('app.id_card_valid_thru') }}: {{ \App\Support\JalaliDate::display($academicYear->end_date) }}</span>
        @endif
    </div>
</div>
