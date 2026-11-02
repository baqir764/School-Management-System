<x-layouts.app :title="! empty($isSender) ? __('app.send_message_to_teacher') : __('app.add_announcement')">
    <div class="d-flex align-items-center gap-2 mb-3">
        <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i></a>
        <h1 class="h4 mb-0">{{ ! empty($isSender) ? __('app.send_message_to_teacher') : __('app.add_announcement') }}</h1>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('announcements.store') }}">
                @csrf
                @include('announcements._form')
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">{{ __('app.save_changes') }}</button>
                    <a href="{{ route('announcements.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</x-layouts.app>
