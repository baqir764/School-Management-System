<x-layouts.app :title="__('app.nav_students')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <form method="GET" class="d-flex gap-2 flex-wrap">
            @if ($showingTrashed)
                <input type="hidden" name="trashed" value="1">
            @endif
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('app.search_students_placeholder') }}">
            <select name="class_id" class="form-select" style="max-width:180px">
                <option value="">{{ __('app.all_classes') }}</option>
                @foreach ($classes as $class)
                    <option value="{{ $class->id }}" @selected(request('class_id') == $class->id)>{{ $class->name }}</option>
                @endforeach
            </select>
            <select name="status" class="form-select" style="max-width:160px">
                <option value="">{{ __('app.all_statuses') }}</option>
                @foreach (['active','inactive','graduated','transferred','expelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ __('app.status_'.$status) }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            @if (request('search') || request('status') || request('class_id'))
                <a href="{{ route('students.index') }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
            @endif
        </form>

        <div class="d-flex gap-2 flex-wrap">
            @if ($showingTrashed)
                <a href="{{ route('students.index') }}" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>{{ __('app.show_active') }}</a>
            @else
                <a href="{{ route('students.export') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('app.students_export') }}</a>
                @can('students.manage')
                    <a href="{{ route('students.index', ['trashed' => 1]) }}" class="btn btn-outline-secondary"><i class="bi bi-trash me-1"></i>{{ __('app.show_deleted') }} @if($trashedCount) ({{ $trashedCount }}) @endif</a>
                    <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importStudentsModal"><i class="bi bi-upload me-1"></i>{{ __('app.students_import') }}</button>
                    <a href="{{ route('students.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_student') }}</a>
                @endcan
            @endif
        </div>
    </div>

    @if ($showingTrashed)
        <div class="alert alert-warning">{{ __('app.trashed_students_hint') }}</div>
    @endif

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <p class="fw-semibold mb-2">{{ __('app.students_import_errors_heading') }}</p>
            <ul class="mb-0">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.field_admission_number') }}</th>
                        <th>{{ __('app.column_name') }}</th>
                        <th>{{ __('app.column_current_class') }}</th>
                        <th>{{ __('app.field_gender') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($students as $student)
                        @php $enrollment = $student->enrollments->first(); @endphp
                        <tr>
                            <td>{{ $student->admission_number }}</td>
                            <td>@if ($showingTrashed){{ $student->fullName() }}@else<a href="{{ route('students.show', $student) }}">{{ $student->fullName() }}</a>@endif</td>
                            <td>{{ $enrollment ? $enrollment->classRoom->name.' - '.$enrollment->section->name : '—' }}</td>
                            <td>{{ __('app.gender_'.$student->gender) }}</td>
                            <td>
                                <span class="badge {{ $student->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __('app.status_'.$student->status) }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    @if ($showingTrashed)
                                        @can('students.manage')
                                            <form method="POST" action="{{ route('students.restore', $student->id) }}" class="d-inline">
                                                @csrf
                                                @method('PATCH')
                                                <button class="btn btn-outline-success" title="{{ __('app.restore') }}"><i class="bi bi-arrow-counterclockwise"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('students.force-destroy', $student->id) }}" data-confirm="{{ __('app.confirm_force_delete_student') }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger" title="{{ __('app.force_delete') }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    @else
                                        <a href="{{ route('students.show', $student) }}" class="btn btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                                        @can('students.manage')
                                            <a href="{{ route('students.edit', $student) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                            <form method="POST" action="{{ route('students.destroy', $student) }}" data-confirm="{{ __('app.confirm_delete_student') }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-secondary" title="{{ __('app.delete') }}"><i class="bi bi-archive"></i></button>
                                            </form>
                                            <form method="POST" action="{{ route('students.force-destroy', $student) }}" data-confirm="{{ __('app.confirm_force_delete_student') }}" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-outline-danger" title="{{ __('app.force_delete') }}"><i class="bi bi-trash"></i></button>
                                            </form>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($students->hasPages())
            <div class="card-footer bg-white">{{ $students->links() }}</div>
        @endif
    </div>

    @can('students.manage')
        <div class="modal fade" id="importStudentsModal" tabindex="-1" aria-labelledby="importStudentsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('students.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="importStudentsModalLabel">{{ __('app.students_import') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">{{ __('app.students_import_hint') }}</p>
                            <input type="file" name="file" accept=".xlsx,.xls" class="form-control @error('file') is-invalid @enderror" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('app.students_import') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @error('file')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('importStudentsModal')).show();
                });
            </script>
        @enderror
    @endcan
</x-layouts.app>
