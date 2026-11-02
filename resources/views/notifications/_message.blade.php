@php
    $data = $notification->data;
@endphp
@if (! empty($data['announcement_id']))
    <span class="badge text-bg-primary me-1">{{ __('app.nav_announcements') }}</span>
    <strong>{{ $data['title'] ?? '' }}</strong>
    @if (! empty($data['excerpt']))
        — {{ $data['excerpt'] }}
    @endif
@else
{{ __('app.fee_reminder_message', [
    'student' => $data['student_name'] ?? '',
    'fee_head' => $data['fee_head'] ?? '',
    'month' => $data['month'] ?? __('app.fee_invoice_month_unspecified'),
    'amount' => number_format($data['amount'] ?? 0, 2).' '.\App\Models\Setting::current()->currency,
    'due_date' => ! empty($data['due_date']) ? \App\Support\JalaliDate::display($data['due_date']) : '—',
]) }}
@endif
