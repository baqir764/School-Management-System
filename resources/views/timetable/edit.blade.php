<x-layouts.app :title="__('app.edit_timetable_entry')">
    <div class="card">
        <div class="card-body p-4">
            <div class="mb-3 text-muted">
                {{ $entry->section->classRoom->name }} — {{ $entry->section->name }} &middot;
                {{ $entry->period->name }} ({{ $entry->period->timeRangeLabel() }}) &middot;
                {{ __('app.day_'.$entry->day_of_week) }}
            </div>

            <form method="POST" action="{{ route('timetable-entries.update', $entry) }}" novalidate>
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.field_subject') }}</label>
                        <select name="subject_id" class="form-select @error('subject_id') is-invalid @enderror" required>
                            <option value="">{{ __('app.select_placeholder') }}</option>
                            @foreach ($subjects as $subject)
                                <option value="{{ $subject->id }}" @selected(old('subject_id', $entry->subject_id) == $subject->id)>{{ $subject->name }}</option>
                            @endforeach
                        </select>
                        @error('subject_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label">{{ __('app.field_subject_teacher') }}</label>
                        <select name="teacher_id" class="form-select @error('teacher_id') is-invalid @enderror" required>
                            <option value="">{{ __('app.select_placeholder') }}</option>
                            @foreach ($teachers as $teacher)
                                <option value="{{ $teacher->id }}" @selected(old('teacher_id', $entry->teacher_id) == $teacher->id)>{{ $teacher->fullName() }}</option>
                            @endforeach
                        </select>
                        @error('teacher_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="mt-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('app.save_changes') }}</button>
                    <a href="{{ route('timetable.index', ['section_id' => $entry->section_id]) }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
