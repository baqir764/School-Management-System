@php
    $editing = isset($category);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_category_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $category->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_expense_category_name_placeholder') }}" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $editing ? $category->is_active : true))>
            <label class="form-check-label" for="is_active">{{ __('app.field_active') }}</label>
        </div>
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('app.field_description') }}</label>
        <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description', $editing ? $category->description : '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_expense_category') }}</button>
    <a href="{{ route('expense-categories.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
