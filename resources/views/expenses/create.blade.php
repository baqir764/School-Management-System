@php
    $currentJalaliYear = \App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0];
@endphp
<x-layouts.app :title="__('app.record_expense')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.record_expense') }}</h1>
        <a href="{{ route('expenses.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}
        </a>
    </div>

    <div class="card" style="max-width:640px">
        <div class="card-body">
            <form method="POST" action="{{ route('expenses.store') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_expense_category') }}</label>
                    <select name="expense_category_id" class="form-select @error('expense_category_id') is-invalid @enderror" required>
                        <option value="">{{ __('app.select_placeholder') }}</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('expense_category_id') == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_title') }}</label>
                    <input type="text" name="title" value="{{ old('title') }}" class="form-control @error('title') is-invalid @enderror" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_amount') }}</label>
                    <div class="input-group">
                        <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" required>
                        <span class="input-group-text">{{ \App\Models\Setting::current()->currency }}</span>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_expense_date') }}</label>
                    <x-jalali-date name="expense_date" :value="\App\Support\JalaliDate::today()" required :max-year="$currentJalaliYear" />
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_payment_method') }}</label>
                    <select name="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                        @foreach (\App\Models\Expense::METHODS as $method)
                            <option value="{{ $method }}" @selected(old('payment_method') === $method)>{{ __('app.payment_method_'.$method) }}</option>
                        @endforeach
                    </select>
                    @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label class="form-label">{{ __('app.field_remarks') }}</label>
                    <input type="text" name="remarks" value="{{ old('remarks') }}" maxlength="255" class="form-control @error('remarks') is-invalid @enderror">
                    @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button type="submit" class="btn btn-primary">{{ __('app.record_expense') }}</button>
            </form>
        </div>
    </div>
</x-layouts.app>
