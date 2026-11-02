@php
    $currency = \App\Models\Setting::current()->currency;
    $status = $feeInvoice->status();
    $balance = $feeInvoice->balance();
@endphp
<x-layouts.app :title="$feeInvoice->feeStructure->name.' — '.$feeInvoice->student->fullName()">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ $feeInvoice->student->fullName() }} — {{ $feeInvoice->feeStructure->name }}</h1>
        <div class="d-flex gap-2">
            @can('fees.manage')
                <a href="{{ route('fee-invoices.edit', $feeInvoice) }}" class="btn btn-outline-secondary">
                    <i class="bi bi-percent me-1"></i>{{ __('app.edit_discount') }}
                </a>
            @endcan
            <a href="{{ route('fee-invoices.print', $feeInvoice) }}" target="_blank" class="btn btn-outline-secondary">
                <i class="bi bi-printer me-1"></i>{{ __('app.print_invoice_notice') }}
            </a>
            <a href="{{ route('fee-invoices.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>{{ __('app.nav_fee_invoices') }}
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-7">
            <div class="card mb-3">
                <div class="card-header bg-white">{{ __('app.field_fee_head') }}</div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">{{ __('app.field_student') }}</dt>
                        <dd class="col-sm-8">{{ $feeInvoice->student->fullName() }} ({{ $feeInvoice->student->admission_number }})</dd>

                        <dt class="col-sm-4">{{ __('app.field_academic_year') }}</dt>
                        <dd class="col-sm-8">{{ $feeInvoice->academicYear->name }}</dd>

                        <dt class="col-sm-4">{{ __('app.field_amount') }}</dt>
                        <dd class="col-sm-8">{{ number_format($feeInvoice->amount, 2) }} {{ $currency }}</dd>

                        @if ($feeInvoice->discount_amount > 0)
                            <dt class="col-sm-4">{{ __('app.field_discount_amount') }}</dt>
                            <dd class="col-sm-8">{{ number_format($feeInvoice->discount_amount, 2) }} {{ $currency }}</dd>

                            <dt class="col-sm-4 fw-semibold">{{ __('app.field_payable_amount') }}</dt>
                            <dd class="col-sm-8 fw-semibold">{{ number_format($feeInvoice->payableAmount(), 2) }} {{ $currency }}</dd>
                        @endif

                        <dt class="col-sm-4">{{ __('app.field_paid') }}</dt>
                        <dd class="col-sm-8">{{ number_format($feeInvoice->totalPaid(), 2) }} {{ $currency }}</dd>

                        <dt class="col-sm-4">{{ __('app.field_balance') }}</dt>
                        <dd class="col-sm-8">{{ number_format($balance, 2) }} {{ $currency }}</dd>

                        @if ($feeInvoice->due_date)
                            <dt class="col-sm-4">{{ __('app.field_due_date') }}</dt>
                            <dd class="col-sm-8">{{ \App\Support\JalaliDate::display($feeInvoice->due_date) }}</dd>
                        @endif

                        <dt class="col-sm-4">{{ __('app.column_status') }}</dt>
                        <dd class="col-sm-8">
                            <span class="badge {{ ['unpaid' => 'text-bg-danger', 'partial' => 'text-bg-warning', 'paid' => 'text-bg-success'][$status] }}">{{ __('app.status_'.$status) }}</span>
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white">{{ __('app.payment_history') }}</div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.field_receipt_number') }}</th>
                                <th>{{ __('app.field_payment_date') }}</th>
                                <th>{{ __('app.field_amount') }}</th>
                                <th>{{ __('app.field_payment_method') }}</th>
                                <th>{{ __('app.field_received_by') }}</th>
                                <th class="text-end">{{ __('app.column_actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($feeInvoice->payments as $payment)
                                <tr>
                                    <td>{{ $payment->receipt_number }}</td>
                                    <td>{{ \App\Support\JalaliDate::display($payment->payment_date) }}</td>
                                    <td>{{ number_format($payment->amount, 2) }} {{ $currency }}</td>
                                    <td>{{ __('app.payment_method_'.$payment->payment_method) }}</td>
                                    <td>{{ $payment->receivedBy->name ?? '—' }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('fee-payments.receipt', $payment) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.receipt_title') }}"><i class="bi bi-receipt"></i></a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">{{ __('app.no_payments_yet') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            @can('fees.collect')
                <div class="card">
                    <div class="card-header bg-white">{{ __('app.record_payment') }}</div>
                    <div class="card-body">
                        @if ($balance <= 0)
                            <p class="text-muted mb-0">{{ __('app.invoice_fully_paid') }}</p>
                        @else
                            <form method="POST" action="{{ route('fee-invoices.payments.store', $feeInvoice) }}">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">{{ __('app.field_amount') }}</label>
                                    <input type="number" step="0.01" min="0.01" max="{{ $balance }}" name="amount" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" required>
                                    @error('amount')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('app.field_payment_date') }}</label>
                                    <x-jalali-date name="payment_date" :value="\App\Support\JalaliDate::today()" required :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0]" />
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('app.field_payment_method') }}</label>
                                    <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                                        @foreach (\App\Models\FeePayment::METHODS as $method)
                                            <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('app.payment_method_'.$method) }}</option>
                                        @endforeach
                                    </select>
                                    @error('payment_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">{{ __('app.field_remarks') }}</label>
                                    <input type="text" name="remarks" value="{{ old('remarks') }}" maxlength="255" class="form-control @error('remarks') is-invalid @enderror">
                                    @error('remarks')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <button type="submit" class="btn btn-primary w-100">{{ __('app.record_payment') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endcan
        </div>
    </div>
</x-layouts.app>
