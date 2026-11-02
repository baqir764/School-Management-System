@php
    $editing = isset($shift);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_shift_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $shift->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_shift_name_placeholder') }}" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_start_time') }}</label>
        <input type="time" name="start_time" value="{{ old('start_time', $editing ? substr($shift->start_time, 0, 5) : '') }}" class="form-control @error('start_time') is-invalid @enderror" required>
        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $editing ? $shift->is_active : true))>
            <label class="form-check-label" for="is_active">{{ __('app.field_active') }}</label>
        </div>
    </div>

    <div class="col-12">
        <div class="form-text">{{ __('app.field_shift_help') }}</div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_shift') }}</button>
    <a href="{{ route('shifts.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
