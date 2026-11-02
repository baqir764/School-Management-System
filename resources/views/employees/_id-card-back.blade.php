@php
    // Same compact-JSON convention as the student card, plus the "t" type
    // so every scanner tells staff cards apart from student ones.
    $qrPayload = json_encode([
        't' => 'employee',
        'a' => $employee->employee_code,
        'n' => $employee->fullName(),
        'c' => $employee->position ?? '',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp
<div class="id-card id-card-back">
    <div class="id-card-header">
        @if ($school->logo_path)
            <img src="{{ Storage::url($school->logo_path) }}" alt="" class="id-card-logo">
        @else
            <span class="id-card-logo-placeholder"><i class="bi bi-mortarboard"></i></span>
        @endif
        <div class="id-card-school-name">{{ $school->school_name }}</div>
    </div>
    <div class="id-card-qr-wrap">
        <div class="id-card-qr" data-qrcode="{{ $qrPayload }}"></div>
    </div>
    <div class="id-card-admission">{{ $employee->employee_code }}</div>
    <div class="id-card-back-footer">
        <div class="id-card-signature">{{ __('app.id_card_signature') }}</div>
        {{ __('app.id_card_back_note') }}
    </div>
</div>
