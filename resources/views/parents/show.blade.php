<x-layouts.app :title="$parent->fullName()">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $parent->fullName() }}</h1>
        <div class="d-flex gap-2">
            @can('parents.manage')
                <a href="{{ route('parents.edit', $parent) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('app.edit') }}</a>
            @endcan
            <a href="{{ route('parents.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card text-center">
                <div class="card-body">
                    @if ($parent->photo_path)
                        <img src="{{ Storage::url($parent->photo_path) }}" alt="" class="img-thumbnail mb-3" style="max-height:180px">
                    @else
                        <div class="border rounded d-flex align-items-center justify-content-center text-muted mx-auto mb-3" style="height:180px;width:180px">
                            <i class="bi bi-person fs-1"></i>
                        </div>
                    @endif
                    <p class="mb-1"><strong>{{ __('app.field_phone') }}:</strong> {{ $parent->phone }}</p>
                    <p class="mb-1"><strong>{{ __('app.field_email') }}:</strong> {{ $parent->email ?? '—' }}</p>
                    <p class="mb-1"><strong>{{ __('app.field_occupation') }}:</strong> {{ $parent->occupation ?? '—' }}</p>
                    @if ($parent->user)
                        <p class="mb-1"><strong>{{ __('app.field_link_user_account') }}:</strong> {{ $parent->user->email }}</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-white fw-semibold">{{ __('app.nav_students') }}</div>
                <div class="card-body">
                    @can('parents.manage')
                        <form method="POST" action="{{ route('parents.link-student', $parent) }}" class="row g-2 mb-3 align-items-end">
                            @csrf
                            <div class="col-md-5">
                                <label class="form-label small">{{ __('app.nav_students') }}</label>
                                <select name="student_id" class="form-select form-select-sm" required>
                                    <option value="">{{ __('app.select_placeholder') }}</option>
                                    @foreach ($linkableStudents as $student)
                                        <option value="{{ $student->id }}">{{ $student->fullName() }} ({{ $student->admission_number }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('app.field_relationship') }}</label>
                                <select name="relationship" class="form-select form-select-sm" required>
                                    <option value="Father">{{ __('app.relationship_father') }}</option>
                                    <option value="Mother">{{ __('app.relationship_mother') }}</option>
                                    <option value="Guardian">{{ __('app.relationship_guardian') }}</option>
                                    <option value="Other">{{ __('app.relationship_other') }}</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_primary_contact" value="1" id="is_primary_contact">
                                    <label class="form-check-label small" for="is_primary_contact">{{ __('app.primary_contact') }}</label>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-link-45deg me-1"></i>{{ __('app.link') }}</button>
                            </div>
                        </form>
                    @endcan

                    <ul class="list-group list-group-flush">
                        @forelse ($parent->students as $student)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <a href="{{ route('students.show', $student) }}">{{ $student->fullName() }}</a>
                                    <div class="text-muted small">{{ $student->admission_number }} · {{ $student->pivot->relationship }} @if($student->pivot->is_primary_contact) · {{ __('app.primary_contact') }} @endif</div>
                                </div>
                                @can('parents.manage')
                                    <form method="POST" action="{{ route('parents.unlink-student', [$parent, $student]) }}" data-confirm="{{ __('app.confirm_unlink_student') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="{{ __('app.unlink') }}"><i class="bi bi-x-lg"></i></button>
                                    </form>
                                @endcan
                            </li>
                        @empty
                            <li class="list-group-item text-muted">{{ __('app.no_data_yet') }}</li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
