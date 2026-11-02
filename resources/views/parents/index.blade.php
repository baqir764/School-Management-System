<x-layouts.app :title="__('app.nav_parents')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('app.search_parents_placeholder') }}">
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            @if (request('search'))
                <a href="{{ route('parents.index') }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
            @endif
        </form>

        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('parents.export') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('app.parents_export') }}</a>
            @can('parents.manage')
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importParentsModal"><i class="bi bi-upload me-1"></i>{{ __('app.parents_import') }}</button>
                <a href="{{ route('parents.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_parent') }}</a>
            @endcan
        </div>
    </div>

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <p class="fw-semibold mb-2">{{ __('app.parents_import_errors_heading') }}</p>
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
                        <th>{{ __('app.column_name') }}</th>
                        <th>{{ __('app.field_phone') }}</th>
                        <th>{{ __('app.field_occupation') }}</th>
                        <th>{{ __('app.column_children') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($parents as $parent)
                        <tr>
                            <td><a href="{{ route('parents.show', $parent) }}">{{ $parent->fullName() }}</a></td>
                            <td>{{ $parent->phone }}</td>
                            <td>{{ $parent->occupation ?? '—' }}</td>
                            <td>{{ $parent->students_count }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('parents.show', $parent) }}" class="btn btn-outline-secondary" title="{{ __('app.view') }}"><i class="bi bi-eye"></i></a>
                                    @can('parents.manage')
                                        <a href="{{ route('parents.edit', $parent) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                        <form method="POST" action="{{ route('parents.destroy', $parent) }}" data-confirm="{{ __('app.confirm_delete_parent') }}">
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
                            <td colspan="5" class="text-center text-muted py-4">{{ __('app.no_data_yet') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($parents->hasPages())
            <div class="card-footer bg-white">{{ $parents->links() }}</div>
        @endif
    </div>

    @can('parents.manage')
        <div class="modal fade" id="importParentsModal" tabindex="-1" aria-labelledby="importParentsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('parents.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="importParentsModalLabel">{{ __('app.parents_import') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">{{ __('app.parents_import_hint') }}</p>
                            <input type="file" name="file" accept=".xlsx,.xls" class="form-control @error('file') is-invalid @enderror" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('app.parents_import') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        @error('file')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('importParentsModal')).show();
                });
            </script>
        @enderror
    @endcan
</x-layouts.app>
