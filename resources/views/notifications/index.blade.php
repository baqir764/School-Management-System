<x-layouts.app :title="__('app.notifications')">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h1 class="h4 mb-0">{{ __('app.notifications') }}</h1>
        @if ($notifications->whereNull('read_at')->isNotEmpty())
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('app.mark_all_read') }}</button>
            </form>
        @endif
    </div>

    <div class="card">
        <div class="list-group list-group-flush">
            @forelse ($notifications as $notification)
                <div class="list-group-item d-flex justify-content-between align-items-start gap-2 {{ $notification->read_at ? '' : 'bg-light' }}">
                    <div class="{{ $notification->read_at ? '' : 'fw-semibold' }}">
                        @include('notifications._message', ['notification' => $notification])
                        <div class="text-muted small mt-1">
                            {{ \App\Support\JalaliDate::display(\App\Support\JalaliDate::fromGregorian($notification->created_at)) }}
                        </div>
                    </div>
                    @if (! $notification->read_at)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm">{{ __('app.mark_read') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="list-group-item text-center text-muted py-4">{{ __('app.no_notifications') }}</div>
            @endforelse
        </div>
        @if ($notifications->hasPages())
            <div class="card-footer bg-white">{{ $notifications->links() }}</div>
        @endif
    </div>
</x-layouts.app>
