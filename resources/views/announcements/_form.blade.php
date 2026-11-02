@php $editing = isset($announcement) && $announcement->exists; @endphp

<div class="mb-3">
    <label class="form-label">{{ __('app.field_title') }} <span class="text-danger">*</span></label>
    <input type="text" name="title" value="{{ old('title', $announcement->title ?? '') }}" class="form-control @error('title') is-invalid @enderror" required maxlength="255">
    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label class="form-label">{{ __('app.field_body') }} <span class="text-danger">*</span></label>
    <textarea name="body" rows="5" class="form-control @error('body') is-invalid @enderror" required>{{ old('body', $announcement->body ?? '') }}</textarea>
    @error('body')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

@if (! empty($isSender))
    <div class="mb-3">
        <label class="form-label">{{ __('app.field_teacher') }} <span class="text-danger">*</span></label>
        <div class="form-text mb-2">{{ __('app.announcement_teacher_help') }}</div>
        @php $selectedTeachers = old('teacher_ids', isset($announcement) ? (array) ($announcement->target_user_ids ?? []) : []); @endphp
        @if ($teachers->isEmpty())
            <div class="alert alert-warning mb-0">{{ __('app.announcement_no_teachers') }}</div>
        @else
            <div class="row g-2">
                @foreach ($teachers as $option)
                    <div class="col-12 col-md-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="teacher_ids[]" value="{{ $option['teacher']->id }}" id="teacher_{{ $option['teacher']->id }}" @checked(in_array((int) $option['teacher']->id, array_map('intval', (array) $selectedTeachers), true))>
                            <label class="form-check-label" for="teacher_{{ $option['teacher']->id }}">
                                {{ $option['teacher']->fullName() }}
                                @if (! empty($option['subjects']))
                                    <span class="text-muted small">— {{ implode(', ', $option['subjects']) }}</span>
                                @endif
                            </label>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @error('teacher_ids')<div class="text-danger small">{{ $message }}</div>@enderror
    </div>
@else
<div class="mb-3">
    <label class="form-label">{{ __('app.field_audience') }}</label>
    <div class="form-text mb-2">{{ __('app.announcement_audience_help') }}</div>
    @php $selected = old('target_roles', $announcement->target_roles ?? []); @endphp
    <div class="row g-2">
        @foreach ($roles as $role)
            <div class="col-6 col-md-3">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="target_roles[]" value="{{ $role }}" id="role_{{ $role }}" @checked(in_array($role, (array) $selected, true))>
                    <label class="form-check-label" for="role_{{ $role }}">{{ $role }}</label>
                </div>
            </div>
        @endforeach
    </div>
    <div class="form-text">{{ __('app.announcement_audience_empty_help') }}</div>
    @error('target_roles')<div class="text-danger small">{{ $message }}</div>@enderror
</div>
@endif
