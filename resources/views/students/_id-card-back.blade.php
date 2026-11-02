@php
    // The QR carries the student's key facts as compact JSON: admission
    // number (the stable system key, also printed as text below the QR),
    // full name, and current class. Scanners read all three at once, so
    // the offline APK fills name+class straight from the card with no
    // roster file needed. Keys stay single-letter to keep the code small
    // and reliably printable. The web scan page still resolves the student
    // by the admission number inside (its regex matches within JSON), and
    // old single-token cards keep working everywhere - every reader below
    // accepts both forms.
    $qrEnrollment = $student->currentEnrollment();
    $qrClass = $qrEnrollment
        ? trim(($qrEnrollment->classRoom?->name ?? '').' - '.($qrEnrollment->section?->name ?? ''), " \t\n\r\0\x0B-")
        : '';
    $qrPayload = json_encode([
        'a' => $student->admission_number,
        'n' => $student->fullName(),
        'c' => $qrClass,
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
    <div class="id-card-admission">{{ $student->admission_number }}</div>
    <div class="id-card-back-footer">
        <div class="id-card-signature">{{ __('app.id_card_signature') }}</div>
        {{ __('app.id_card_back_note') }}
    </div>
</div>
