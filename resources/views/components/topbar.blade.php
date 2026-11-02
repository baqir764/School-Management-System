@props(['title' => null])

<header class="sms-topbar d-flex align-items-center justify-content-between px-3 sticky-top">
    <div class="d-flex align-items-center gap-2">
        <button class="btn btn-light d-lg-none" type="button" id="smsSidebarToggle" aria-label="{{ __('app.toggle_navigation') }}" aria-controls="smsSidebar" aria-expanded="false">
            <i class="bi bi-list"></i>
        </button>
        <div>
            @isset($title)
                <h1 class="h5 mb-0">{{ $title }}</h1>
            @endisset
            @isset($breadcrumb)
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 small">
                        {{ $breadcrumb }}
                    </ol>
                </nav>
            @endisset
        </div>
    </div>

    <div class="d-flex align-items-center gap-2">
        @php
            // Guarded with Schema::hasTable() the same way
            // DashboardStatsService guards not-yet-migrated tables - the
            // notifications table (see its migration) may not exist yet on
            // an app that hasn't run `php artisan migrate` since this
            // feature was added, and the topbar renders on every page for
            // every signed-in user, so this must never throw.
            $notificationsAvailable = \Illuminate\Support\Facades\Schema::hasTable('notifications');
            $unreadNotifications = $notificationsAvailable ? auth()->user()->unreadNotifications()->limit(5)->get() : collect();
            $unreadNotificationsCount = $notificationsAvailable ? auth()->user()->unreadNotifications()->count() : 0;
        @endphp
        @if ($notificationsAvailable)
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary position-relative" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-bell"></i>
                    @if ($unreadNotificationsCount > 0)
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
                        </span>
                    @endif
                </button>
                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 320px; max-height: 420px; overflow-y: auto;">
                    <div class="dropdown-header d-flex justify-content-between align-items-center">
                        <span>{{ __('app.notifications') }}</span>
                        @if ($unreadNotificationsCount > 0)
                            <form method="POST" action="{{ route('notifications.read-all') }}">
                                @csrf
                                <button type="submit" class="btn btn-link btn-sm p-0">{{ __('app.mark_all_read') }}</button>
                            </form>
                        @endif
                    </div>
                    @forelse ($unreadNotifications as $notification)
                        <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-wrap small fw-semibold">
                                @include('notifications._message', ['notification' => $notification])
                            </button>
                        </form>
                    @empty
                        <div class="dropdown-item-text text-muted small">{{ __('app.no_notifications') }}</div>
                    @endforelse
                    <div class="dropdown-item-text text-center border-top">
                        <a href="{{ route('notifications.index') }}" class="small">{{ __('app.view_all_notifications') }}</a>
                    </div>
                </div>
            </div>
        @endif

        <div class="dropdown">
            <button class="btn btn-sm btn-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                {{ config('app.available_locales.'.app()->getLocale().'.native', app()->getLocale()) }}
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                @foreach (config('app.available_locales') as $code => $meta)
                    <li>
                        <form method="POST" action="{{ route('locale.update', $code) }}">
                            @csrf
                            <button type="submit" class="dropdown-item {{ app()->getLocale() === $code ? 'active' : '' }}">
                                {{ $meta['native'] }}
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-person-circle"></i>
                <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li><h6 class="dropdown-header">{{ auth()->user()->primaryRoleLabel() }}</h6></li>
                <li><a class="dropdown-item" href="{{ route('account.password.edit') }}"><i class="bi bi-key me-2"></i>{{ __('app.nav_change_password') }}</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>{{ __('app.nav_logout') }}</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
