@php
    $editing = isset($section);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_class_name') }}</label>
        <select name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
            <option value="">{{ __('app.select_placeholder') }}</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected(old('class_id', $editing ? $section->class_id : '') == $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_section_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $section->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_section_name_placeholder') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_class_teacher') }}</label>
        <select name="class_teacher_id" class="form-select @error('class_teacher_id') is-invalid @enderror">
            <option value="">{{ __('app.none') }}</option>
            @foreach ($teachers as $teacher)
                <option value="{{ $teacher->id }}" @selected(old('class_teacher_id', $editing ? $section->class_teacher_id : '') == $teacher->id)>{{ $teacher->fullName() }}</option>
            @endforeach
        </select>
        @error('class_teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_capacity') }}</label>
        <input type="number" min="1" name="capacity" value="{{ old('capacity', $editing ? $section->capacity : '') }}" class="form-control">
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_room_number') }}</label>
        <input type="text" name="room_number" value="{{ old('room_number', $editing ? $section->room_number : '') }}" class="form-control">
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_section') }}</button>
    <a href="{{ route('sections.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
