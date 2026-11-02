@php
    $currency = \App\Models\Setting::current()->currency;
    $invoice = $payment->invoice;
@endphp
<x-layouts.app :title="__('app.receipt_title')">
    <style>
        @media print {
            .sms-sidebar, .sms-topbar, .no-print, .toast-container { display: none !important; }
            main { padding: 0 !important; }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
        <h1 class="h4 mb-0">{{ __('app.receipt_title') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('fee-invoices.show', $invoice) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('app.back_to_invoice') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>{{ __('app.print_receipt') }}
            </button>
        </div>
    </div>

    <div class="card" style="max-width:640px">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h2 class="h5 mb-0">{{ \App\Models\Setting::current()->school_name }}</h2>
                    <p class="text-muted mb-0">{{ __('app.receipt_title') }}</p>
                </div>
                <div class="text-end">
                    <div class="fw-semibold">{{ $payment->receipt_number }}</div>
                    <div class="text-muted">{{ \App\Support\JalaliDate::display($payment->payment_date) }}</div>
                </div>
            </div>

            <dl class="row mb-0">
                <dt class="col-sm-5">{{ __('app.field_student') }}</dt>
                <dd class="col-sm-7">{{ $invoice->student->fullName() }} ({{ $invoice->student->admission_number }})</dd>

                <dt class="col-sm-5">{{ __('app.field_fee_head') }}</dt>
                <dd class="col-sm-7">{{ $invoice->feeStructure->name }}</dd>

                <dt class="col-sm-5">{{ __('app.field_amount') }}</dt>
                <dd class="col-sm-7">{{ number_format($payment->amount, 2) }} {{ $currency }}</dd>

                <dt class="col-sm-5">{{ __('app.field_payment_method') }}</dt>
                <dd class="col-sm-7">{{ __('app.payment_method_'.$payment->payment_method) }}</dd>

                @if ($payment->remarks)
                    <dt class="col-sm-5">{{ __('app.field_remarks') }}</dt>
                    <dd class="col-sm-7">{{ $payment->remarks }}</dd>
                @endif

                <dt class="col-sm-5">{{ __('app.field_received_by') }}</dt>
                <dd class="col-sm-7">{{ $payment->receivedBy->name ?? '—' }}</dd>

                <dt class="col-sm-5">{{ __('app.field_balance') }}</dt>
                <dd class="col-sm-7">{{ number_format($invoice->balance(), 2) }} {{ $currency }}</dd>
            </dl>
        </div>
    </div>
</x-layouts.app>
