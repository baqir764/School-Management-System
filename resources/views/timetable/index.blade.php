<x-layouts.app :title="__('app.nav_timetable')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.nav_timetable') }}</h1>
        @can('timetable.manage')
            <a href="{{ route('periods.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-clock me-1"></i>{{ __('app.nav_periods') }}</a>
        @endcan
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('timetable.index') }}" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label mb-1">{{ __('app.field_select_section') }}</label>
                    <select name="section_id" class="form-select" onchange="this.form.submit()">
                        @forelse ($sections as $option)
                            <option value="{{ $option->id }}" @selected($section && $section->id === $option->id)>{{ $option->classRoom->name }} — {{ $option->name }}</option>
                        @empty
                            <option value="">{{ __('app.no_data_yet') }}</option>
                        @endforelse
                    </select>
                </div>
                <div class="col-auto">
                    <noscript><button type="submit" class="btn btn-outline-secondary">{{ __('app.filter') }}</button></noscript>
                </div>
            </form>
        </div>
    </div>

    @if (! $section)
        <div class="card">
            <div class="card-body text-center text-muted py-5">{{ __('app.timetable_no_section_selected') }}</div>
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table align-middle mb-0 text-center">
                    <thead class="table-light">
                        <tr>
                            <th class="text-start">{{ __('app.field_period') }}</th>
                            @foreach ($displayDays as $day)
                                <th>{{ __('app.day_'.$day) }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($periods as $period)
                            <tr>
                                <td class="text-start">
                                    <div class="fw-semibold">{{ $period->name }}</div>
                                    <div class="small text-muted">{{ $period->timeRangeLabel() }}</div>
                                </td>
                                @foreach ($displayDays as $day)
                                    @php $entry = $entries->get($period->id.'-'.$day); @endphp
                                    <td style="min-width:150px">
                                        @if ($entry)
                                            <div class="small fw-semibold">{{ $entry->subject->name }}</div>
                                            <div class="small text-muted">{{ $entry->teacher->fullName() }}</div>
                                            @can('timetable.manage')
                                                <div class="btn-group btn-group-sm mt-1">
                                                    <a href="{{ route('timetable-entries.edit', $entry) }}" class="btn btn-outline-secondary btn-sm" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>
                                                    <form method="POST" action="{{ route('timetable-entries.destroy', $entry) }}" data-confirm="{{ __('app.confirm_delete_timetable_entry') }}">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="btn btn-outline-danger btn-sm" title="{{ __('app.delete') }}"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                </div>
                                            @endcan
                                        @else
                                            @can('timetable.manage')
                                                <a href="{{ route('timetable-entries.create', ['section_id' => $section->id, 'period_id' => $period->id, 'day_of_week' => $day]) }}" class="btn btn-outline-primary btn-sm" title="{{ __('app.assign_slot') }}"><i class="bi bi-plus-lg"></i></a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endcan
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($displayDays) + 1 }}" class="text-center text-muted py-4">{{ __('app.no_periods_defined') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
