@php
    $currentEnrollment = $student->enrollments->firstWhere('status', 'active');
@endphp
<x-layouts.app :title="$student->fullName()">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ $student->fullName() }}</h1>
        <div class="d-flex gap-2">
            <a href="{{ route('students.id-card', $student) }}" class="btn btn-outline-secondary btn-sm" target="_blank"><i class="bi bi-person-vcard me-1"></i>{{ __('app.students_id_card') }}</a>
            @can('students.manage')
                <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil me-1"></i>{{ __('app.edit') }}</a>
            @endcan
            <a href="{{ route('students.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card text-center mb-4">
                <div class="card-body">
                    @if ($student->photo_path)
                        <img src="{{ Storage::url($student->photo_path) }}" alt="" class="img-thumbnail mb-3" style="max-height:180px">
                    @else
                        <div class="border rounded d-flex align-items-center justify-content-center text-muted mx-auto mb-3" style="height:180px;width:180px">
                            <i class="bi bi-person fs-1"></i>
                        </div>
                    @endif
                    <p class="mb-1"><strong>{{ __('app.field_admission_number') }}:</strong> {{ $student->admission_number }}</p>
                    <p class="mb-1"><strong>{{ __('app.column_status') }}:</strong> {{ __('app.status_'.$student->status) }}</p>
                    <p class="mb-1"><strong>{{ __('app.column_current_class') }}:</strong>
                        {{ $currentEnrollment ? $currentEnrollment->classRoom->name.' - '.$currentEnrollment->section->name : __('app.not_enrolled') }}
                    </p>
                    <p class="mb-1"><strong>{{ __('app.field_phone') }}:</strong> {{ $student->phone ?? '—' }}</p>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header bg-white fw-semibold">{{ __('app.nav_parents') }}</div>
                <ul class="list-group list-group-flush">
                    @forelse ($student->parents as $parent)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <a href="{{ route('parents.show', $parent) }}">{{ $parent->fullName() }}</a>
                                <div class="text-muted small">{{ $parent->pivot->relationship }} @if($parent->pivot->is_primary_contact) · {{ __('app.primary_contact') }} @endif</div>
                            </div>
                        </li>
                    @empty
                        <li class="list-group-item text-muted">{{ __('app.no_data_yet') }}</li>
                    @endforelse
                </ul>
            </div>

            @can('students.manage')
                <div class="card border-danger mb-4">
                    <div class="card-header bg-white fw-semibold text-danger">{{ __('app.danger_zone_title') }}</div>
                    <div class="card-body">
                        <p class="text-muted small">{{ __('app.delete_student_hint') }}</p>
                        <div class="d-flex gap-2 flex-wrap">
                            <form method="POST" action="{{ route('students.destroy', $student) }}" data-confirm="{{ __('app.confirm_delete_student') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-secondary"><i class="bi bi-archive me-1"></i>{{ __('app.delete') }}</button>
                            </form>
                            <form method="POST" action="{{ route('students.force-destroy', $student) }}" data-confirm="{{ __('app.confirm_force_delete_student') }}">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>{{ __('app.force_delete') }}</button>
                            </form>
                        </div>
                    </div>
                </div>
            @endcan
        </div>

        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    {{ __('app.nav_enrollment_history') }}
                    @can('students.manage')
                        <a href="{{ route('students.enroll.create', $student) }}" class="btn btn-sm btn-outline-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.enroll_student') }}</a>
                    @endcan
                </div>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>{{ __('app.nav_academic_years') }}</th>
                                <th>{{ __('app.column_current_class') }}</th>
                                <th>{{ __('app.field_roll_number') }}</th>
                                <th>{{ __('app.column_status') }}</th>
                                @can('students.manage')<th></th>@endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($student->enrollments as $enrollment)
                                <tr>
                                    <td>{{ $enrollment->academicYear->name }}</td>
                                    <td>{{ $enrollment->classRoom->name }} - {{ $enrollment->section->name }}</td>
                                    <td>{{ $enrollment->roll_number ?? '—' }}</td>
                                    <td>{{ __('app.enrollment_status_'.$enrollment->status) }}</td>
                                    @can('students.manage')
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ route('students.enroll.edit', [$student, $enrollment]) }}" class="btn btn-outline-secondary" title="{{ __('app.transfer_student') }}"><i class="bi bi-arrow-left-right"></i></a>
                                                <form method="POST" action="{{ route('students.enroll.destroy', [$student, $enrollment]) }}" data-confirm="{{ __('app.confirm_remove_enrollment') }}" class="d-inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="btn btn-sm btn-outline-danger" title="{{ __('app.force_delete') }}"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-3">{{ __('app.no_data_yet') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-white fw-semibold">{{ __('app.nav_documents') }}</div>
                <div class="card-body">
                    @can('students.manage')
                        <form method="POST" action="{{ route('students.documents.store', $student) }}" enctype="multipart/form-data" class="row g-2 mb-3 align-items-end">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label small">{{ __('app.field_document_type') }}</label>
                                <select name="document_type" class="form-select form-select-sm" required>
                                    @foreach (['birth_certificate','national_id','transfer_certificate','photo','other'] as $type)
                                        <option value="{{ $type }}">{{ __('app.document_type_'.$type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('app.field_document_title') }}</label>
                                <input type="text" name="title" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small">{{ __('app.field_file') }}</label>
                                <input type="file" name="file" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-1">
                                <button class="btn btn-sm btn-primary w-100"><i class="bi bi-upload"></i></button>
                            </div>
                        </form>
                        @error('file')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        @error('title')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                    @endcan

                    <ul class="list-group list-group-flush">
                        @forelse ($student->documents as $document)
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div>{{ $document->title }}</div>
                                    <div class="text-muted small">{{ __('app.document_type_'.$document->document_type) }} · {{ $document->original_filename }}</div>
                                </div>
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('students.documents.download', [$student, $document]) }}" class="btn btn-outline-secondary" title="{{ __('app.download') }}"><i class="bi bi-download"></i></a>
                                    @can('students.manage')
                                        <form method="POST" action="{{ route('students.documents.destroy', [$student, $document]) }}" data-confirm="{{ __('app.confirm_delete_document') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
                                </div>
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
