<x-layouts.app :title="__('app.generate_invoices_for', ['name' => $feeStructure->name])">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.generate_invoices_for', ['name' => $feeStructure->name]) }}</h1>
        <a href="{{ route('fee-structures.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('app.nav_fee_structure') }}
        </a>
    </div>

    <div class="card" style="max-width:640px">
        <div class="card-body">
            <dl class="row mb-3">
                <dt class="col-sm-5">{{ __('app.field_academic_year') }}</dt>
                <dd class="col-sm-7">{{ $feeStructure->academicYear->name }}</dd>

                <dt class="col-sm-5">{{ __('app.field_class_name') }}</dt>
                <dd class="col-sm-7">{{ $feeStructure->classRoom->name ?? __('app.all_classes') }}</dd>

                <dt class="col-sm-5">{{ __('app.field_amount') }}</dt>
                <dd class="col-sm-7">{{ number_format($feeStructure->amount, 2) }} {{ \App\Models\Setting::current()->currency }}</dd>

                <dt class="col-sm-5">{{ __('app.eligible_students_count', ['count' => $eligibleCount]) }}</dt>
                <dd class="col-sm-7"></dd>

                <dt class="col-sm-5">{{ __('app.already_invoiced_count', ['count' => $alreadyInvoiced]) }}</dt>
                <dd class="col-sm-7"></dd>
            </dl>

            @if ($eligibleCount - $alreadyInvoiced <= 0)
                <p class="text-muted mb-0">{{ __('app.no_eligible_students') }}</p>
            @else
                <form method="POST" action="{{ route('fee-invoices.store', $feeStructure) }}" data-confirm="{{ __('app.confirm_generate_invoices') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">{{ __('app.field_due_date') }}</label>
                        <x-jalali-date name="due_date" />
                    </div>
                    <button type="submit" class="btn btn-primary">{{ __('app.generate_invoices') }}</button>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
