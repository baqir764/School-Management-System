@php
    $editing = isset($academicYear);
@endphp

<div class="row g-3">
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_year_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $academicYear->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_year_name_placeholder') }}" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_start_date') }}</label>
        <x-jalali-date name="start_date" :value="$editing ? $academicYear->start_date : null" :required="true" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 2" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] + 3" />
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_end_date') }}</label>
        <x-jalali-date name="end_date" :value="$editing ? $academicYear->end_date : null" :required="true" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 2" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] + 3" />
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('app.field_remarks') }}</label>
        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks', $editing ? $academicYear->remarks : '') }}</textarea>
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $editing ? $academicYear->is_active : false))>
            <label class="form-check-label" for="is_active">{{ __('app.field_make_active_year') }}</label>
        </div>
        <div class="form-text">{{ __('app.field_make_active_year_help') }}</div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_academic_year') }}</button>
    <a href="{{ route('academic-years.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
