<x-layouts.app :title="__('app.nav_teachers')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('app.search_teachers_placeholder') }}">
            <select name="status" class="form-select" style="max-width:180px">
                <option value="">{{ __('app.all_statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('app.status_active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('app.status_inactive') }}</option>
                <option value="terminated" @selected(request('status') === 'terminated')>{{ __('app.status_terminated') }}</option>
            </select>
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            @if (request('search') || request('status'))
                <a href="{{ route('teachers.index') }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
            @endif
        </form>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('teachers.export') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('app.teachers_export') }}</a>
            @can('teachers.manage')
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importTeachersModal"><i class="bi bi-upload me-1"></i>{{ __('app.teachers_import') }}</button>
                <a href="{{ route('teachers.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_teacher') }}</a>
            @endcan
        </div>
    </div>

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <p class="fw-semibold mb-2">{{ __('app.teachers_import_errors_heading') }}</p>
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
                        <th>{{ __('app.field_employee_code') }}</th>
                        <th>{{ __('app.column_name') }}</th>
                        <th>{{ __('app.field_phone') }}</th>
                        <th>{{ __('app.field_employment_type') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($teachers as $teacher)
                        <tr>
                            <td>{{ $teacher->employee_code }}</td>
                            <td><a href="{{ route('teachers.show', $teacher) }}">{{ $teacher->fullName() }}</a></td>
                            <td>{{ $teacher->phone ?? '—' }}</td>
                            <td>{{ __('app.employment_'.$teacher->employment_type) }}</td>
                            <td>
                                <span class="badge {{ $teacher->status === 'active' ? 'text-bg-success' : 'text-bg-secondary' }}">{{ __('app.status_'.$teacher->status) }}</span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('teachers.show', $teacher) }}" class="btn btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                                    @can('payroll.pay')
                                        <a href="{{ route('salary-payments.create', ['payee_type' => 'teacher', 'payee_id' => $teacher->id]) }}" class="btn btn-outline-secondary" title="{{ __('app.pay_salary') }}"><i class="bi bi-cash-coin"></i></a>
                                    @endcan
                                    @can('teachers.manage')
                                        <a href="{{ route('teachers.edit', $teacher) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('teachers.destroy', $teacher) }}" data-confirm="{{ __('app.confirm_delete_teacher') }}">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                        </form>
                                    @endcan
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
        @if ($teachers->hasPages())
            <div class="card-footer bg-white">{{ $teachers->links() }}</div>
        @endif
    </div>

    @can('teachers.manage')
        <div class="modal fade" id="importTeachersModal" tabindex="-1" aria-labelledby="importTeachersModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('teachers.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="importTeachersModalLabel">{{ __('app.teachers_import') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">{{ __('app.teachers_import_hint') }}</p>
                            <input type="file" name="file" accept=".xlsx,.xls" class="form-control @error('file') is-invalid @enderror" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('app.teachers_import') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @error('file')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('importTeachersModal')).show();
                });
            </script>
        @enderror
    @endcan
</x-layouts.app>
