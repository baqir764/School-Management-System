@php
    $school = \App\Models\Setting::current();
@endphp
<x-layouts.app :title="__('app.fee_invoice_print_title')">
    <style>
        {{-- A4 portrait, explicitly - not left to whatever the browser's print
             dialog happens to default to. --}}
        @page {
            size: A4 portrait;
            margin: 12mm;
        }

        @media print {
            .sms-sidebar, .sms-topbar, .no-print, .toast-container { display: none !important; }
            main { padding: 0 !important; }
        }

        {{-- Each invoice is its own bordered, cuttable slip - not a shared
             table - because the whole point is printing a page, cutting it
             into individual pieces with scissors, and handing/sending one
             slip per family. Exactly 10 slips (2 columns x 5 rows) per A4
             portrait page: the nth-child rule below forces a page break
             before the 11th, 21st, ... slip so a multi-page batch keeps
             that count precisely rather than however many happen to fit. --}}
        .print-slip {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            box-sizing: border-box;
            border: 1px dashed #666;
            border-radius: 4px;
            padding: 10px 12px;
            margin: 0 1% 10px 1%;
            min-height: 40mm;
            page-break-inside: avoid;
            font-size: 0.88rem;
            line-height: 1.5;
        }

        .print-slip:nth-child(10n+1):not(:first-child) {
            page-break-before: always;
        }

        .print-slip .school-name {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 0.82em;
            color: #666;
            border-bottom: 1px solid #ccc;
            padding-bottom: 4px;
            margin-bottom: 6px;
        }

        .print-slip .school-logo,
        .print-slip .school-logo-placeholder {
            height: 20px;
            width: 20px;
            flex: 0 0 auto;
            object-fit: cover;
            border-radius: 3px;
        }

        .print-slip .school-logo-placeholder {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef1f4;
            color: #99a1a8;
            font-size: 0.7rem;
        }

        .print-slip .amount {
            font-weight: 600;
        }

        .print-slip .slip-notice {
            margin-top: 6px;
            padding-top: 5px;
            border-top: 1px dashed #ccc;
            font-size: 0.76em;
            font-style: italic;
            color: #666;
        }

        @media (max-width: 900px) {
            .print-slip { width: 100%; margin: 0 0 10px 0; }
        }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 no-print">
        <h1 class="h4 mb-0">{{ __('app.fee_invoice_print_title') }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('fee-invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}
            </a>
            <button type="button" class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer me-1"></i>{{ __('app.print_receipt') }}
            </button>
        </div>
    </div>

    @if ($invoices->isEmpty())
        <p class="text-muted no-print">{{ __('app.no_data_yet') }}</p>
    @else
        <div>
            @foreach ($invoices as $invoice)
                @php
                    $student = $invoice->student;
                    $enrollment = $student->enrollments->first();
                    $className = $enrollment?->classRoom?->name;
                    $sectionName = $enrollment?->section?->name;
                    $monthLabel = $invoice->due_date
                        ? \App\Support\JalaliDate::monthYearDisplay(substr($invoice->due_date, 0, 7))
                        : __('app.fee_invoice_month_unspecified');
                @endphp
                <div class="print-slip">
                    <div class="school-name">
                        @if ($school->logo_path)
                            <img src="{{ Storage::url($school->logo_path) }}" alt="" class="school-logo">
                        @else
                            <span class="school-logo-placeholder"><i class="bi bi-mortarboard"></i></span>
                        @endif
                        <span>{{ $school->school_name }}</span>
                    </div>
                    <div><strong>{{ $student->fullName() }}</strong> ({{ $student->admission_number }})</div>
                    <div>{{ __('app.field_class_name') }}: {{ $className ?? '—' }}{{ $sectionName ? ' / '.$sectionName : '' }}</div>
                    <div>{{ $invoice->feeStructure->name }} — {{ $monthLabel }}</div>
                    <div class="amount">{{ __('app.field_balance') }}: {{ number_format($invoice->balance(), 2) }} {{ $school->currency }}</div>
                    @if ($invoice->due_date)
                        <div>{{ __('app.field_due_date') }}: {{ \App\Support\JalaliDate::display($invoice->due_date) }}</div>
                    @endif
                    <div class="slip-notice">{{ __('app.fee_invoice_print_notice') }}</div>
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.app>
