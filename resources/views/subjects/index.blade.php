<x-layouts.app :title="__('app.nav_subjects')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_subjects') }}</h1>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('subjects.export') }}" class="btn btn-outline-secondary"><i class="bi bi-file-earmark-excel me-1"></i>{{ __('app.subjects_export') }}</a>
            @can('academics.manage')
                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importSubjectsModal"><i class="bi bi-upload me-1"></i>{{ __('app.subjects_import') }}</button>
                <a href="{{ route('subjects.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_subject') }}</a>
            @endcan
        </div>
    </div>

    @if (session('import_errors'))
        <div class="alert alert-warning">
            <p class="fw-semibold mb-2">{{ __('app.subjects_import_errors_heading') }}</p>
            <ul class="mb-0">
                @foreach (session('import_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- One collapsible group per class (collapsed by default), plus a
         trailing group for subjects with no specific class (class_id
         null - offered school-wide). Grouping this way, instead of one
         long table sorted only by name, lets the user open just the
         class they're looking for. --}}
    <div class="accordion" id="subjectsAccordion">
        @foreach ($classes as $class)
            @php $collapseId = 'subjects-class-'.$class->id; @endphp
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-{{ $collapseId }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                        <span class="flex-grow-1">{{ $class->name }}</span>
                        <span class="badge text-bg-secondary ms-2">{{ $class->subjects->count() }}</span>
                    </button>
                </h2>
                <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $collapseId }}" data-bs-parent="#subjectsAccordion">
                    <div class="accordion-body p-0">
                        @include('subjects._table', ['subjects' => $class->subjects])
                    </div>
                </div>
            </div>
        @endforeach

        @if ($unassignedSubjects->isNotEmpty())
            @php $collapseId = 'subjects-unassigned'; @endphp
            <div class="accordion-item">
                <h2 class="accordion-header" id="heading-{{ $collapseId }}">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#{{ $collapseId }}" aria-expanded="false" aria-controls="{{ $collapseId }}">
                        <span class="flex-grow-1">{{ __('app.all_classes') }}</span>
                        <span class="badge text-bg-secondary ms-2">{{ $unassignedSubjects->count() }}</span>
                    </button>
                </h2>
                <div id="{{ $collapseId }}" class="accordion-collapse collapse" aria-labelledby="heading-{{ $collapseId }}" data-bs-parent="#subjectsAccordion">
                    <div class="accordion-body p-0">
                        @include('subjects._table', ['subjects' => $unassignedSubjects])
                    </div>
                </div>
            </div>
        @endif
    </div>

    @can('academics.manage')
        <div class="modal fade" id="importSubjectsModal" tabindex="-1" aria-labelledby="importSubjectsModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" action="{{ route('subjects.import') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="importSubjectsModalLabel">{{ __('app.subjects_import') }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('app.cancel') }}"></button>
                        </div>
                        <div class="modal-body">
                            <p class="text-muted small">{{ __('app.subjects_import_hint') }}</p>
                            <input type="file" name="file" accept=".xlsx,.xls" class="form-control @error('file') is-invalid @enderror" required>
                            @error('file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">{{ __('app.cancel') }}</button>
                            <button type="submit" class="btn btn-primary">{{ __('app.subjects_import') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- A failed import (e.g. wrong file type) redirects back with the
             "file" field's error - reopen the modal automatically so the
             message is where the user is looking, instead of a validation
             error sitting behind a closed modal they'd have to reopen
             themselves to see. --}}
        @error('file')
            <script>
                document.addEventListener('DOMContentLoaded', () => {
                    new bootstrap.Modal(document.getElementById('importSubjectsModal')).show();
                });
            </script>
        @enderror
    @endcan
</x-layouts.app>
