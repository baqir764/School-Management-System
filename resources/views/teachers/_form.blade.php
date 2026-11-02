@php
    $editing = isset($teacher);
@endphp

<div class="row g-3">
    <div class="col-md-3 text-center">
        <div class="mb-2">
            @if ($editing && $teacher->photo_path)
                <img src="{{ Storage::url($teacher->photo_path) }}" alt="" class="img-thumbnail" style="max-height:120px">
            @else
                <div class="border rounded d-flex align-items-center justify-content-center text-muted mx-auto" style="height:120px;width:120px">
                    <i class="bi bi-person fs-1"></i>
                </div>
            @endif
        </div>
        <input type="file" name="photo" class="form-control form-control-sm @error('photo') is-invalid @enderror" accept="image/*">
        @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-9">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_first_name') }}</label>
                <input type="text" name="first_name" value="{{ old('first_name', $editing ? $teacher->first_name : '') }}" class="form-control @error('first_name') is-invalid @enderror" required autofocus>
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_last_name') }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', $editing ? $teacher->last_name : '') }}" class="form-control @error('last_name') is-invalid @enderror" required>
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_father_name') }}</label>
                <input type="text" name="father_name" value="{{ old('father_name', $editing ? $teacher->father_name : '') }}" class="form-control">
            </div>

            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_gender') }}</label>
                <select name="gender" class="form-select">
                    <option value="">{{ __('app.select_placeholder') }}</option>
                    <option value="male" @selected(old('gender', $editing ? $teacher->gender : '') === 'male')>{{ __('app.gender_male') }}</option>
                    <option value="female" @selected(old('gender', $editing ? $teacher->gender : '') === 'female')>{{ __('app.gender_female') }}</option>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_date_of_birth') }}</label>
                <x-jalali-date name="date_of_birth" :value="$editing ? $teacher->date_of_birth : null" />
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_national_id') }}</label>
                <input type="text" name="national_id" value="{{ old('national_id', $editing ? $teacher->national_id : '') }}" class="form-control">
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_phone') }}</label>
        <input type="text" name="phone" value="{{ old('phone', $editing ? $teacher->phone : '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_email') }}</label>
        <input type="email" name="email" value="{{ old('email', $editing ? $teacher->email : '') }}" class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_link_user_account') }}</label>
        <select name="user_id" class="form-select @error('user_id') is-invalid @enderror">
            <option value="">{{ __('app.none') }}</option>
            @foreach ($linkableUsers as $user)
                <option value="{{ $user->id }}" @selected(old('user_id', $editing ? $teacher->user_id : '') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
            @endforeach
        </select>
        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12"><label class="form-label">{{ __('app.field_address') }}</label>
        <input type="text" name="address" value="{{ old('address', $editing ? $teacher->address : '') }}" class="form-control">
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_qualification') }}</label>
        <input type="text" name="qualification" value="{{ old('qualification', $editing ? $teacher->qualification : '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_specialization') }}</label>
        <input type="text" name="specialization" value="{{ old('specialization', $editing ? $teacher->specialization : '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_joining_date') }}</label>
        <x-jalali-date name="joining_date" :value="$editing ? $teacher->joining_date : null" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 15" />
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_employment_type') }}</label>
        <select name="employment_type" class="form-select" required>
            <option value="full_time" @selected(old('employment_type', $editing ? $teacher->employment_type : 'full_time') === 'full_time')>{{ __('app.employment_full_time') }}</option>
            <option value="part_time" @selected(old('employment_type', $editing ? $teacher->employment_type : '') === 'part_time')>{{ __('app.employment_part_time') }}</option>
            <option value="contract" @selected(old('employment_type', $editing ? $teacher->employment_type : '') === 'contract')>{{ __('app.employment_contract') }}</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_shift') }}</label>
        <select name="shift_id" class="form-select @error('shift_id') is-invalid @enderror">
            <option value="">{{ __('app.field_shift_none') }}</option>
            @foreach ($shifts as $shift)
                <option value="{{ $shift->id }}" @selected(old('shift_id', $editing ? $teacher->shift_id : '') == $shift->id)>{{ $shift->name }} ({{ $shift->startTimeLabel() }})</option>
            @endforeach
        </select>
        @error('shift_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_status') }}</label>
        <select name="status" class="form-select" required>
            <option value="active" @selected(old('status', $editing ? $teacher->status : 'active') === 'active')>{{ __('app.status_active') }}</option>
            <option value="inactive" @selected(old('status', $editing ? $teacher->status : '') === 'inactive')>{{ __('app.status_inactive') }}</option>
            <option value="terminated" @selected(old('status', $editing ? $teacher->status : '') === 'terminated')>{{ __('app.status_terminated') }}</option>
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_monthly_salary') }}</label>
        <div class="input-group">
            <input type="number" step="0.01" min="0" name="monthly_salary" value="{{ old('monthly_salary', $editing ? $teacher->monthly_salary : '') }}" class="form-control @error('monthly_salary') is-invalid @enderror">
            <span class="input-group-text">{{ \App\Models\Setting::current()->currency }}</span>
            @error('monthly_salary')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_teacher') }}</button>
    <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
