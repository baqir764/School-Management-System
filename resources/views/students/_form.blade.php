@php
    $editing = isset($student);
@endphp

<div class="row g-3">
    <div class="col-md-3 text-center">
        <div class="mb-2">
            @if ($editing && $student->photo_path)
                <img src="{{ Storage::url($student->photo_path) }}" alt="" class="img-thumbnail" style="max-height:120px">
            @else
                <div class="border rounded d-flex align-items-center justify-content-center text-muted mx-auto" style="height:120px;width:120px">
                    <i class="bi bi-person fs-1"></i>
                </div>
            @endif
        </div>
        <input type="file" name="photo" class="form-control form-control-sm @error('photo') is-invalid @enderror" accept="image/*">
        @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        @if ($editing)
            <div class="form-text mt-2">{{ __('app.field_admission_number') }}: <strong>{{ $student->admission_number }}</strong></div>
        @else
            <div class="form-text mt-2"><i class="bi bi-info-circle me-1"></i>{{ __('app.admission_number_year_note', ['year' => \App\Models\Student::admissionNumberYear()]) }}</div>
        @endif
    </div>

    <div class="col-md-9">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_first_name') }}</label>
                <input type="text" name="first_name" value="{{ old('first_name', $editing ? $student->first_name : '') }}" class="form-control @error('first_name') is-invalid @enderror" required autofocus>
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_last_name') }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', $editing ? $student->last_name : '') }}" class="form-control @error('last_name') is-invalid @enderror" required>
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_father_name') }}</label>
                <input type="text" name="father_name" value="{{ old('father_name', $editing ? $student->father_name : '') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_grandfather_name') }}</label>
                <input type="text" name="grandfather_name" value="{{ old('grandfather_name', $editing ? $student->grandfather_name : '') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_gender') }}</label>
                <select name="gender" class="form-select @error('gender') is-invalid @enderror" required>
                    <option value="">{{ __('app.select_placeholder') }}</option>
                    <option value="male" @selected(old('gender', $editing ? $student->gender : '') === 'male')>{{ __('app.gender_male') }}</option>
                    <option value="female" @selected(old('gender', $editing ? $student->gender : '') === 'female')>{{ __('app.gender_female') }}</option>
                </select>
                @error('gender')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_date_of_birth') }}</label>
                <x-jalali-date name="date_of_birth" :value="$editing ? $student->date_of_birth : null" />
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_place_of_birth') }}</label>
        <input type="text" name="place_of_birth" value="{{ old('place_of_birth', $editing ? $student->place_of_birth : '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_national_id') }}</label>
        <input type="text" name="national_id" value="{{ old('national_id', $editing ? $student->national_id : '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_nationality') }}</label>
        <input type="text" name="nationality" value="{{ old('nationality', $editing ? $student->nationality : 'Afghan') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_religion') }}</label>
        <input type="text" name="religion" value="{{ old('religion', $editing ? $student->religion : '') }}" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_blood_group') }}</label>
        <input type="text" name="blood_group" value="{{ old('blood_group', $editing ? $student->blood_group : '') }}" class="form-control" placeholder="A+, O-, ...">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_province') }}</label>
        <input type="text" name="province" value="{{ old('province', $editing ? $student->province : '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_district') }}</label>
        <input type="text" name="district" value="{{ old('district', $editing ? $student->district : '') }}" class="form-control">
    </div>
    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_phone') }}</label>
        <input type="text" name="phone" value="{{ old('phone', $editing ? $student->phone : '') }}" class="form-control">
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_address') }}</label>
        <input type="text" name="address" value="{{ old('address', $editing ? $student->address : '') }}" class="form-control">
    </div>
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_email') }}</label>
        <input type="email" name="email" value="{{ old('email', $editing ? $student->email : '') }}" class="form-control @error('email') is-invalid @enderror">
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-5">
        <label class="form-label">{{ __('app.field_admission_date') }}</label>
        <x-jalali-date name="admission_date" :value="$editing ? $student->admission_date : \App\Support\JalaliDate::today()" :required="true" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 15" />
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_status') }}</label>
        <select name="status" class="form-select" required>
            @foreach (['active','inactive','graduated','transferred','expelled'] as $status)
                <option value="{{ $status }}" @selected(old('status', $editing ? $student->status : 'active') === $status)>{{ __('app.status_'.$status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_previous_school') }}</label>
        <input type="text" name="previous_school" value="{{ old('previous_school', $editing ? $student->previous_school : '') }}" class="form-control">
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('app.field_medical_notes') }}</label>
        <textarea name="medical_notes" class="form-control" rows="2">{{ old('medical_notes', $editing ? $student->medical_notes : '') }}</textarea>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_student') }}</button>
    <a href="{{ $editing ? route('students.show', $student) : route('students.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
