@php
    $editing = isset($period);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_period_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $period->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_period_name_placeholder') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_start_time') }}</label>
        <input type="time" name="start_time" value="{{ old('start_time', $editing ? substr($period->start_time, 0, 5) : '') }}" class="form-control @error('start_time') is-invalid @enderror" required>
        @error('start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_end_time') }}</label>
        <input type="time" name="end_time" value="{{ old('end_time', $editing ? substr($period->end_time, 0, 5) : '') }}" class="form-control @error('end_time') is-invalid @enderror" required>
        @error('end_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_sort_order') }}</label>
        <input type="number" min="0" max="1000" name="sort_order" value="{{ old('sort_order', $editing ? $period->sort_order : 0) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
        <div class="form-text">{{ __('app.field_sort_order_help') }}</div>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_period') }}</button>
    <a href="{{ route('periods.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
