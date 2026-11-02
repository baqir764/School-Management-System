@php $currency = \App\Models\Setting::current()->currency; @endphp
<div class="card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-semibold">{{ __('app.salary_payment_history') }}</span>
        @can('payroll.pay')
            <a href="{{ route('salary-payments.create', ['payee_type' => $payeeType, 'payee_id' => $payee->id]) }}" class="btn btn-primary btn-sm">
                <i class="bi bi-cash-coin me-1"></i>{{ __('app.pay_salary') }}
            </a>
        @endcan
    </div>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('app.field_salary_month') }}</th>
                    <th>{{ __('app.field_amount') }}</th>
                    <th>{{ __('app.field_discount_amount') }}</th>
                    <th>{{ __('app.field_net_amount') }}</th>
                    <th>{{ __('app.field_payment_date') }}</th>
                    <th>{{ __('app.field_payment_method') }}</th>
                    <th class="text-end">{{ __('app.column_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($payee->salaryPayments()->orderByDesc('salary_month')->get() as $payment)
                    <tr>
                        <td>{{ \App\Support\JalaliDate::monthYearDisplay($payment->salary_month) }}</td>
                        <td>{{ number_format($payment->amount, 2) }} {{ $currency }}</td>
                        <td>{{ $payment->discount_amount > 0 ? number_format($payment->discount_amount, 2).' '.$currency : '—' }}</td>
                        <td>{{ number_format($payment->netAmount(), 2) }} {{ $currency }}</td>
                        <td>{{ \App\Support\JalaliDate::display($payment->payment_date) }}</td>
                        <td>{{ __('app.payment_method_'.$payment->payment_method) }}</td>
                        <td class="text-end">
                            <a href="{{ route('salary-payments.receipt', $payment) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.receipt_title') }}"><i class="bi bi-receipt"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center text-muted py-4">{{ __('app.no_payments_yet') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
