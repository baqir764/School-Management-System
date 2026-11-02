@php
    $editing = isset($class);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_class_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $class->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_class_name_placeholder') }}" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_numeric_level') }}</label>
        <input type="number" min="0" name="numeric_level" value="{{ old('numeric_level', $editing ? $class->numeric_level : '') }}" class="form-control @error('numeric_level') is-invalid @enderror" required>
        <div class="form-text">{{ __('app.field_numeric_level_help') }}</div>
        @error('numeric_level')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_shift') }}</label>
        <select name="shift_id" class="form-select @error('shift_id') is-invalid @enderror">
            <option value="">{{ __('app.field_shift_none') }}</option>
            @foreach ($shifts as $shift)
                <option value="{{ $shift->id }}" @selected(old('shift_id', $editing ? $class->shift_id : '') == $shift->id)>{{ $shift->name }} ({{ $shift->startTimeLabel() }})</option>
            @endforeach
        </select>
        <div class="form-text">{{ __('app.field_class_shift_help') }}</div>
        @error('shift_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('app.field_description') }}</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $editing ? $class->description : '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $editing ? $class->is_active : true))>
            <label class="form-check-label" for="is_active">{{ __('app.field_active') }}</label>
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_class') }}</button>
    <a href="{{ route('classes.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
