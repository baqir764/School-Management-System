<x-layouts.app :title="__('app.edit_fee_invoice_discount_for', ['name' => $feeInvoice->student->fullName()])">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.edit_fee_invoice_discount_for', ['name' => $feeInvoice->student->fullName()]) }}</h1>
        <a href="{{ route('fee-invoices.show', $feeInvoice) }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}
        </a>
    </div>

    <div class="card" style="max-width:640px">
        <div class="card-body">
            @php $currency = \App\Models\Setting::current()->currency; @endphp
            <dl class="row mb-3">
                <dt class="col-sm-5">{{ __('app.field_student') }}</dt>
                <dd class="col-sm-7">{{ $feeInvoice->student->fullName() }} ({{ $feeInvoice->student->admission_number }})</dd>

                <dt class="col-sm-5">{{ __('app.field_fee_head') }}</dt>
                <dd class="col-sm-7">{{ $feeInvoice->feeStructure->name }}</dd>

                <dt class="col-sm-5">{{ __('app.field_amount') }}</dt>
                <dd class="col-sm-7">{{ number_format($feeInvoice->amount, 2) }} {{ $currency }}</dd>

                <dt class="col-sm-5">{{ __('app.field_paid') }}</dt>
                <dd class="col-sm-7">{{ number_format($feeInvoice->totalPaid(), 2) }} {{ $currency }}</dd>
            </dl>

            <form method="POST" action="{{ route('fee-invoices.update', $feeInvoice) }}">
                @csrf
                @method('PUT')
                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_discount_amount') }}</label>
                    <input type="number" step="0.01" min="0" max="{{ $feeInvoice->amount }}" id="invoice-discount" name="discount_amount" value="{{ old('discount_amount', $feeInvoice->discount_amount) }}" class="form-control @error('discount_amount') is-invalid @enderror">
                    <div class="form-text">{{ __('app.discount_amount_hint') }}</div>
                    @error('discount_amount')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_payable_amount') }}</label>
                    <input type="text" id="invoice-payable" class="form-control" readonly>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('app.save_changes') }}</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            const amount = {{ (float) $feeInvoice->amount }};
            const discountInput = document.getElementById('invoice-discount');
            const payableOutput = document.getElementById('invoice-payable');

            function updatePayable() {
                const discount = parseFloat(discountInput.value) || 0;
                const payable = Math.max(0, amount - discount);
                payableOutput.value = payable.toFixed(2);
            }

            discountInput.addEventListener('input', updatePayable);
            updatePayable();
        })();
    </script>
</x-layouts.app>
