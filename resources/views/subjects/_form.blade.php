@php
    $editing = isset($subject);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_subject_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $subject->name : '') }}" class="form-control @error('name') is-invalid @enderror" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_subject_code') }}</label>
        <input type="text" name="code" value="{{ old('code', $editing ? $subject->code : '') }}" class="form-control @error('code') is-invalid @enderror" placeholder="{{ __('app.field_subject_code_placeholder') }}" required>
        @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_class_name') }}</label>
        <select name="class_id" class="form-select @error('class_id') is-invalid @enderror">
            <option value="">{{ __('app.all_classes') }}</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected(old('class_id', $editing ? $subject->class_id : '') == $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_subject_teacher') }}</label>
        <select name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror">
            <option value="">{{ __('app.none') }}</option>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected(old('teacher_id', $editing ? $subject->teacher_id : '') == $teacher->id)>{{ $teacher->fullName() }}</option>
            @endforeach
        </select>
        @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">{{ __('app.field_full_marks') }}</label>
        <input type="number" min="1" name="full_marks" value="{{ old('full_marks', $editing ? $subject->full_marks : 100) }}" class="form-control @error('full_marks') is-invalid @enderror" required>
        @error('full_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-2">
        <label class="form-label">{{ __('app.field_pass_marks') }}</label>
        <input type="number" min="0" name="pass_marks" value="{{ old('pass_marks', $editing ? $subject->pass_marks : 40) }}" class="form-control @error('pass_marks') is-invalid @enderror" required>
        @error('pass_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <div class="form-check form-switch">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $editing ? $subject->is_active : true))>
            <label class="form-check-label" for="is_active">{{ __('app.field_active') }}</label>
        </div>
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_subject') }}</button>
    <a href="{{ route('subjects.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
