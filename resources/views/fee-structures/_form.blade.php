@php
    $editing = isset($feeStructure);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_fee_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $feeStructure->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_fee_name_placeholder') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_academic_year') }}</label>
        <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
            <option value="">{{ __('app.select_placeholder') }}</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}" @selected(old('academic_year_id', $editing ? $feeStructure->academic_year_id : '') == $year->id)>{{ $year->name }}</option>
            @endforeach
        </select>
        @error('academic_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_class_name') }}</label>
        <select name="class_id" class="form-select @error('class_id') is-invalid @enderror">
            <option value="">{{ __('app.all_classes') }}</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected(old('class_id', $editing ? $feeStructure->class_id : '') == $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        <div class="form-text">{{ __('app.field_class_optional_help') }}</div>
        @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_amount') }}</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="amount" value="{{ old('amount', $editing ? $feeStructure->amount : '') }}" class="form-control @error('amount') is-invalid @enderror" required>
            <span class="input-group-text">{{ \App\Models\Setting::current()->currency }}</span>
            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_frequency') }}</label>
        <select name="frequency" class="form-select @error('frequency') is-invalid @enderror" required>
            @foreach (\App\Models\FeeStructure::FREQUENCIES as $frequency)
                <option value="{{ $frequency }}" @selected(old('frequency', $editing ? $feeStructure->frequency : 'term') === $frequency)>{{ __('app.frequency_'.$frequency) }}</option>
            @endforeach
        </select>
        @error('frequency')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" class="form-check-input" id="is_active" @checked(old('is_active', $editing ? $feeStructure->is_active : true)) >
            <label class="form-check-label" for="is_active">{{ __('app.field_is_active') }}</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('app.field_description') }}</label>
        <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description', $editing ? $feeStructure->description : '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_fee_structure') }}</button>
    <a href="{{ route('fee-structures.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
