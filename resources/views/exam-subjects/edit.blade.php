<x-layouts.app :title="__('app.edit_exam_subject_for', ['name' => $examSubject->subject->name])">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.edit_exam_subject_for', ['name' => $examSubject->subject->name]) }}</h1>
        <a href="{{ route('exams.show', $examSubject->exam) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('exam-subjects.update', $examSubject) }}" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label class="form-label">{{ __('app.field_subject') }}</label>
                    <input type="text" value="{{ $examSubject->subject->name }}" class="form-control" disabled>
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ __('app.field_full_marks') }}</label>
                    <input type="number" min="1" max="1000" name="full_marks" value="{{ old('full_marks', $examSubject->full_marks) }}" class="form-control @error('full_marks') is-invalid @enderror" required>
                    @error('full_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-4">
                    <label class="form-label">{{ __('app.field_pass_marks') }}</label>
                    <input type="number" min="0" max="1000" name="pass_marks" value="{{ old('pass_marks', $examSubject->pass_marks) }}" class="form-control @error('pass_marks') is-invalid @enderror" required>
                    @error('pass_marks')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">{{ __('app.field_exam_date') }}</label>
                    <x-jalali-date
                        name="exam_date"
                        :value="$examSubject->exam_date"
                        :min-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] - 1"
                        :max-year="\App\Support\JalaliDate::parts(\App\Support\JalaliDate::today())[0] + 1"
                    />
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">{{ __('app.save_changes') }}</button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
