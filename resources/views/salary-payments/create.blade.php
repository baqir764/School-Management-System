@php
    $backRoute = $payeeType === 'teacher' ? route('teachers.show', $payee) : route('employees.show', $payee);
    $currentJalaliYear = \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
    $selectedMonth = old('salary_month.month', \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[1]);
    $selectedYear = old('salary_month.year', $currentJalaliYear);
@endphp
<x-layouts.app :title="__('app.pay_salary_for', ['name' => $payee->fullName()])">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.pay_salary_for', ['name' => $payee->fullName()]) }}</h1>
        <a href="{{ $backRoute }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}
        </a>
    </div>

    <div class="card" style="max-width:640px">
        <div class="card-body">
            <dl class="row mb-3">
                <dt class="col-sm-5">{{ __('app.field_payee_type') }}</dt>
                <dd class="col-sm-7">{{ $payeeType === 'teacher' ? __('app.payee_type_teacher') : __('app.payee_type_employee') }}</dd>

                <dt class="col-sm-5">{{ __('app.field_monthly_salary') }}</dt>
                <dd class="col-sm-7">{{ $payee->monthly_salary !== null ? number_format($payee->monthly_salary, 2).' '.\App\Models\Setting::current()->currency : '—' }}</dd>
            </dl>

            @error('payee_id')
                <div class="alert alert-danger">{{ $message }}</div>
            @enderror

            <form method="POST" action="{{ route('salary-payments.store') }}">
                @csrf
                <input type="hidden" name="payee_type" value="{{ $payeeType }}">
                <input type="hidden" name="payee_id" value="{{ $payee->id }}">

                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_salary_month') }}</label>
                    <div class="row g-2">
                        <div class="col-7">
                            <select name="salary_month[month]" class="form-select @error('salary_month.month') is-invalid @enderror" required>
                                @foreach (\App\Support\JalaliDate::MONTH_NAMES as $num => $label)
                                    <option value="{{ $num }}" @selected((int) $selectedMonth === $num)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('salary_month.month')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-5">
                            <select name="salary_month[year]" class="form-select @error('salary_month.year') is-invalid @enderror" required>
                                @for ($y = $currentJalaliYear + 1; $y >= $currentJalaliYear - 10; $y--)
                                    <option value="{{ $y }}" @selected((int) $selectedYear === $y)>{{ $y }}</option>
                                @endfor
                            </select>
                            @error('salary_month.year')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_amount') }}</label>
                    <input type="number" step="0.01" min="0.01" id="salary-amount" name="amount" value="{{ old('amount', $payee->monthly_salary) }}" class="form-control @error('amount') is-invalid @enderror" required>
                    @error('amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_discount_amount') }}</label>
                    <input type="number" step="0.01" min="0" id="salary-discount" name="discount_amount" value="{{ old('discount_amount', 0) }}" class="form-control @error('discount_amount') is-invalid @enderror">
                    <div class="form-text">{{ __('app.discount_amount_hint') }}</div>
                    @error('discount_amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_net_amount') }}</label>
                    <input type="text" id="salary-net-amount" class="form-control" readonly>
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_payment_date') }}</label>
                    <x-jalali-date name="payment_date" :value="\App\Support\JalaliDate::today()" required :max-year="$currentJalaliYear" />
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_payment_method') }}</label>
                    <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                        @foreach (\App\Models\SalaryPayment::METHODS as $method)
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

                <button type="submit" class="btn btn-primary">{{ __('app.pay_salary') }}</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const amountInput = document.getElementById('salary-amount');
            const discountInput = document.getElementById('salary-discount');
            const netOutput = document.getElementById('salary-net-amount');

            function updateNet() {
                const amount = parseFloat(amountInput.value) || 0;
                const discount = parseFloat(discountInput.value) || 0;
                const net = Math.max(0, amount - discount);
                netOutput.value = net.toFixed(2);
            }

            amountInput.addEventListener('input', updateNet);
            discountInput.addEventListener('input', updateNet);
            updateNet();
        })();
    </script>
</x-layouts.app>
