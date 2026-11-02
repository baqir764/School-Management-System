<x-layouts.app :title="__('app.enroll_student')">
    <div class="card">
        <div class="card-body p-4">
            <h5 class="mb-3">{{ __('app.enroll_student') }}: {{ $student->fullName() }}</h5>
            <form method="POST" action="{{ route('students.enroll.store', $student) }}" novalidate>
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">{{ __('app.nav_academic_years') }}</label>
                        <select name="academic_year_id" class="form-select @error('academic_year_id') is-invalid @enderror" required>
                            <option value="">{{ __('app.select_placeholder') }}</option>
                            @foreach ($academicYears as $year)
                                <option value="{{ $year->id }}" @selected(old('academic_year_id') == $year->id || $year->is_active)>{{ $year->name }} @if($year->is_active) ({{ __('app.status_active_year') }}) @endif</option>
                            @endforeach
                        </select>
                        @error('academic_year_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">{{ __('app.field_class_name') }}</label>
                        <select name="class_id" id="class_id" class="form-select @error('class_id') is-invalid @enderror" required>
                            <option value="">{{ __('app.select_placeholder') }}</option>
                            @foreach ($classes as $class)
                                <option value="{{ $class->id }}" @selected(old('class_id') == $class->id)>{{ $class->name }}</option>
                            @endforeach
                        </select>
                        @error('class_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">{{ __('app.field_section_name') }}</label>
                        <select name="section_id" id="section_id" class="form-select @error('section_id') is-invalid @enderror" required>
                            <option value="">{{ __('app.select_placeholder') }}</option>
                            @foreach ($sections as $section)
                                <option value="{{ $section->id }}" data-class-id="{{ $section->class_id }}" class="section-option" @selected(old('section_id') == $section->id)>{{ $section->classRoom->name }} - {{ $section->name }}</option>
                            @endforeach
                        </select>
                        @error('section_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">{{ __('app.field_roll_number') }}</label>
                        <input type="text" name="roll_number" value="{{ old('roll_number') }}" class="form-control @error('roll_number') is-invalid @enderror">
                        @error('roll_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">{{ __('app.field_enrollment_date') }}</label>
                        <x-jalali-date name="enrollment_date" :value="\App\Support\JalaliDate::today()" :required="true" :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 1" />
                    </div>

                    <div class="col-12">
                        <label class="form-label">{{ __('app.field_remarks') }}</label>
                        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('app.enroll_student') }}</button>
                    <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Filters the section dropdown to only the sections belonging to the
        // chosen class - plain vanilla JS, consistent with the rest of the
        // app's dependency-free front-end approach.
        document.addEventListener('DOMContentLoaded', function () {
            var classSelect = document.getElementById('class_id');
            var sectionSelect = document.getElementById('section_id');
            var options = Array.prototype.slice.call(sectionSelect.querySelectorAll('.section-option'));

            function applyFilter() {
                var classId = classSelect.value;
                options.forEach(function (option) {
                    var matches = !classId || option.dataset.classId === classId;
                    option.hidden = !matches;
                    if (!matches && option.selected) {
                        sectionSelect.value = '';
                    }
                });
            }

            classSelect.addEventListener('change', applyFilter);
            applyFilter();
        });
    </script>
</x-layouts.app>
