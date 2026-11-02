@php
    $editing = isset($employee);
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_first_name') }}</label>
        <input type="text" name="first_name" value="{{ old('first_name', $editing ? $employee->first_name : '') }}" class="form-control @error('first_name') is-invalid @enderror" required autofocus>
        @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_last_name') }}</label>
        <input type="text" name="last_name" value="{{ old('last_name', $editing ? $employee->last_name : '') }}" class="form-control @error('last_name') is-invalid @enderror" required>
        @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_position') }}</label>
        <input type="text" name="position" value="{{ old('position', $editing ? $employee->position : '') }}" class="form-control @error('position') is-invalid @enderror" placeholder="{{ __('app.field_position_placeholder') }}" required>
        @error('position')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_phone') }}</label>
        <input type="text" name="phone" value="{{ old('phone', $editing ? $employee->phone : '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_national_id') }}</label>
        <input type="text" name="national_id" value="{{ old('national_id', $editing ? $employee->national_id : '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_joining_date') }}</label>
        <x-jalali-date name="joining_date" :value="$editing ? $employee->joining_date : null" />
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_shift') }}</label>
        <select name="shift_id" class="form-select @error('shift_id') is-invalid @enderror">
            <option value="">{{ __('app.field_shift_none') }}</option>
            @foreach ($shifts as $shift)
                <option value="{{ $shift->id }}" @selected(old('shift_id', $editing ? $employee->shift_id : '') == $shift->id)>{{ $shift->name }} ({{ $shift->startTimeLabel() }})</option>
            @endforeach
        </select>
        @error('shift_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_monthly_salary') }}</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="monthly_salary" value="{{ old('monthly_salary', $editing ? $employee->monthly_salary : '') }}" class="form-control @error('monthly_salary') is-invalid @enderror" required>
            <span class="input-group-text">{{ \App\Models\Setting::current()->currency }}</span>
            @error('monthly_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_status') }}</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $editing ? $employee->status : 'active') === 'active')>{{ __('app.status_active') }}</option>
            <option value="inactive" @selected(old('status', $editing ? $employee->status : '') === 'inactive')>{{ __('app.status_inactive') }}</option>
            <option value="terminated" @selected(old('status', $editing ? $employee->status : '') === 'terminated')>{{ __('app.status_terminated') }}</option>
        </select>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_employee') }}</button>
    <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
