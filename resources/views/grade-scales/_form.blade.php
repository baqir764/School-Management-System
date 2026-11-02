@php
    $editing = isset($gradeScale);
@endphp

<div class="row g-3">
    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_grade_name') }}</label>
        <input type="text" name="grade_name" value="{{ old('grade_name', $editing ? $gradeScale->grade_name : '') }}" class="form-control @error('grade_name') is-invalid @enderror" placeholder="{{ __('app.field_grade_name_placeholder') }}" required>
        @error('grade_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_min_percentage') }}</label>
        <input type="number" step="0.01" min="0" max="100" name="min_percentage" value="{{ old('min_percentage', $editing ? $gradeScale->min_percentage : '') }}" class="form-control @error('min_percentage') is-invalid @enderror" required>
        @error('min_percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_max_percentage') }}</label>
        <input type="number" step="0.01" min="0" max="100" name="max_percentage" value="{{ old('max_percentage', $editing ? $gradeScale->max_percentage : '') }}" class="form-control @error('max_percentage') is-invalid @enderror" required>
        @error('max_percentage')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_sort_order') }}</label>
        <input type="number" min="0" max="1000" name="sort_order" value="{{ old('sort_order', $editing ? $gradeScale->sort_order : 0) }}" class="form-control @error('sort_order') is-invalid @enderror" required>
        @error('sort_order')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('app.field_description') }}</label>
        <input type="text" name="description" value="{{ old('description', $editing ? $gradeScale->description : '') }}" class="form-control @error('description') is-invalid @enderror">
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_grade_scale') }}</button>
    <a href="{{ route('grade-scales.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
