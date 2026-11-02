@php
    $editing = isset($exam);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_exam_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $exam->name : '') }}" class="form-control @error('name') is-invalid @enderror" placeholder="{{ __('app.field_exam_name_placeholder') }}" required>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_academic_year') }}</label>
        <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
            <option value="">{{ __('app.select_placeholder') }}</option>
            @foreach ($academicYears as $year)
                <option value="{{ $year->id }}" @selected(old('academic_year_id', $editing ? $exam->academic_year_id : '') == $year->id)>{{ $year->name }}</option>
            @endforeach
        </select>
        @error('academic_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_class_name') }}</label>
        <select name="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
            <option value="">{{ __('app.select_placeholder') }}</option>
            @foreach ($classes as $class)
                <option value="{{ $class->id }}" @selected(old('class_id', $editing ? $exam->class_id : '') == $class->id)>{{ $class->name }}</option>
            @endforeach
        </select>
        @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_term_type') }}</label>
        <select name="term_type" class="form-select @error('term_type') is-invalid @enderror" required>
            @foreach (\App\Models\Exam::TERM_TYPES as $type)
                <option value="{{ $type }}" @selected(old('term_type', $editing ? $exam->term_type : 'final') === $type)>{{ __('app.term_type_'.$type) }}</option>
            @endforeach
        </select>
        @error('term_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_start_date') }}</label>
        <x-jalali-date name="start_date" :value="$editing ? $exam->start_date : null" :required="true" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 1" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] + 1" />
    </div>

    <div class="col-md-3">
        <label class="form-label">{{ __('app.field_end_date') }}</label>
        <x-jalali-date name="end_date" :value="$editing ? $exam->end_date : null" :required="true" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 1" :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] + 1" />
    </div>

    <div class="col-12">
        <label class="form-label">{{ __('app.field_remarks') }}</label>
        <textarea name="remarks" rows="2" class="form-control @error('remarks') is-invalid @enderror">{{ old('remarks', $editing ? $exam->remarks : '') }}</textarea>
        @error('remarks')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_exam') }}</button>
    <a href="{{ $editing ? route('exams.show', $exam) : route('exams.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
