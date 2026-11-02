<x-layouts.app :title="__('app.nav_announcements')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">{{ __('app.nav_announcements') }}</h1>
        @canany(['announcements.manage', 'announcements.send'])
            <a href="{{ route('announcements.create') }}" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>{{ auth()->user()->can('announcements.manage') ? __('app.add_announcement') : __('app.send_message_to_teacher') }}</a>
        @endcanany
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($announcements as $announcement)
                <div class="list-group-item">
                    <div class="d-flex justify-content-between align-items-start gap-2">
                        <div>
                            <a href="{{ route('announcements.show', $announcement) }}" class="fw-semibold text-decoration-none">{{ $announcement->title }}</a>
                            <div class="small text-muted mt-1">
                                {{ __('app.announcement_audience') }}: {{ $announcement->audienceLabel() }} · {{ $announcement->createdBy?->name ?? '—' }} · {{ $announcement->created_at?->diffForHumans() }}
                            </div>
                            <div class="small mt-1 text-truncate" style="max-width: 560px">{{ \Illuminate\Support\Str::limit($announcement->body, 120) }}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-shrink-0">
                            @if ($announcement->is_published)
                                <span class="badge text-bg-success">{{ __('app.status_published') }}</span>
                            @else
                                <span class="badge text-bg-secondary">{{ __('app.status_unpublished') }}</span>
                            @endif
                            @can('announcements.manage')
                                <form method="POST" action="{{ route('announcements.toggle-publish', $announcement) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="btn btn-outline-secondary btn-sm">{{ $announcement->is_published ? __('app.unpublish') : __('app.publish') }}</button>
                                </form>
                            @endcan
                            @if (auth()->user()->can('announcements.manage') || (auth()->user()->can('announcements.send') && (int) $announcement->created_by === (int) auth()->id()))
                                <a href="{{ route('announcements.edit', $announcement) }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-pencil"></i></a>
                                <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" data-confirm="{{ __('app.confirm_delete_announcement') }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            @endif
                        </div>
                    </div>
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">{{ __('app.no_data_yet') }}</div>
            @endforelse
        </div>
        @if ($announcements->hasPages())
            <div class="card-footer bg-white">{{ $announcements->links() }}</div>
        @endif
    </div>
</x-layouts.app>
