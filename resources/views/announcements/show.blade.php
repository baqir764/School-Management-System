<x-layouts.app :title="$announcement->title">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>{{ __('app.back') }}</a>
        @if (auth()->user()->can('announcements.manage') || (auth()->user()->can('announcements.send') && (int) $announcement->created_by === (int) auth()->id()))
            <div class="d-flex gap-2">
                <form method="POST" action="{{ route('announcements.toggle-publish', $announcement) }}">
                    @csrf
                    @method('PATCH')
                    <button class="btn btn-outline-secondary btn-sm">{{ $announcement->is_published ? __('app.unpublish') : __('app.publish') }}</button>
                </form>
                <a href="{{ route('announcements.edit', $announcement) }}" class="btn btn-outline-secondary btn-sm">{{ __('app.edit') }}</a>
                <form method="POST" action="{{ route('announcements.destroy', $announcement) }}" data-confirm="{{ __('app.confirm_delete_announcement') }}">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-outline-danger btn-sm">{{ __('app.delete') }}</button>
                </form>
            </div>
        @endif
    </div>

    <div class="card">
        <div class="card-body">
            <h1 class="h4">{{ $announcement->title }}</h1>
            <div class="text-muted small mb-3">
                {{ __('app.announcement_audience') }}: {{ $announcement->audienceLabel() }} · {{ $announcement->createdBy?->name ?? '—' }} · {{ $announcement->created_at?->diffForHumans() }}
                @if ($announcement->is_published)
                    <span class="badge text-bg-success ms-2">{{ __('app.status_published') }}</span>
                @else
                    <span class="badge text-bg-secondary ms-2">{{ __('app.status_unpublished') }}</span>
                @endif
            </div>
            <div class="border-top pt-3" style="white-space: pre-wrap;">{{ $announcement->body }}</div>
        </div>
    </div>
</x-layouts.app>
