{{--
    Renders one class's subjects inside its accordion body. Pulled out of
    index.blade.php so the exact same table markup is shared between every
    class group and the "all classes" (unassigned, class_id = null) group,
    instead of copy-pasted per group.
--}}
@if ($subjects->isEmpty())
    <p class="text-center text-muted py-4 mb-0">{{ __('app.no_data_yet') }}</p>
@else
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>{{ __('app.field_subject_code') }}</th>
                    <th>{{ __('app.field_subject_name') }}</th>
                    <th>{{ __('app.field_subject_teacher') }}</th>
                    <th>{{ __('app.column_status') }}</th>
                    <th class="text-end">{{ __('app.column_actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($subjects as $subject)
                    <tr>
                        <td>{{ $subject->code }}</td>
                        <td>{{ $subject->name }}</td>
                        <td>{{ $subject->teacher?->fullName() ?? '—' }}</td>
                        <td>
                            @if ($subject->is_active)
                                <span class="badge text-bg-success">{{ __('app.status_active') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('app.status_disabled') }}</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                @can('academics.manage')
                                    <a href="{{ route('subjects.edit', $subject) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                    <form method="POST" action="{{ route('subjects.destroy', $subject) }}" data-confirm="{{ __('app.confirm_delete_subject') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
