<x-layouts.app :title="__('app.nav_users')">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <form method="GET" class="d-flex gap-2">
            <input type="search" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('app.search_users_placeholder') }}">
            <select name="role" class="form-select" style="max-width:200px">
                <option value="">{{ __('app.all_roles') }}</option>
                @foreach ($roles as $role)
                    <option value="{{ $role->name }}" @selected(request('role') === $role->name)>{{ $role->name }}</option>
                @endforeach
            </select>
            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
            @if (request('search') || request('role'))
                <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('app.reset') }}</a>
            @endif
        </form>

        <a href="{{ route('users.create') }}" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>{{ __('app.add_user') }}</a>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>{{ __('app.column_name') }}</th>
                        <th>{{ __('app.column_email') }}</th>
                        <th>{{ __('app.column_roles') }}</th>
                        <th>{{ __('app.column_status') }}</th>
                        <th>{{ __('app.column_last_login') }}</th>
                        <th class="text-end">{{ __('app.column_actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td>
                                @foreach ($user->roles as $role)
                                    <span class="badge text-bg-light border">{{ $role->name }}</span>
                                @endforeach
                            </td>
                            <td>
                                @if ($user->is_active)
                                    <span class="badge text-bg-success">{{ __('app.status_active') }}</span>
                                @else
                                    <span class="badge text-bg-secondary">{{ __('app.status_disabled') }}</span>
                                @endif
                            </td>
                            <td class="text-muted small">{{ $user->last_login_at?->diffForHumans() ?? __('app.never') }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('users.edit', $user) }}" class="btn btn-outline-secondary" title="{{ __('app.edit') }}"><i class="bi bi-pencil"></i></a>

                                    <form method="POST" action="{{ route('users.reset-password', $user) }}" data-confirm="{{ __('app.confirm_reset_password') }}">
                                        @csrf
                                        <button class="btn btn-outline-secondary" title="{{ __('app.reset_password') }}"><i class="bi bi-key"></i></button>
                                    </form>

                                    <form method="POST" action="{{ route('users.toggle-active', $user) }}" data-confirm="{{ __('app.confirm_toggle_status') }}">
                                        @csrf
                                        @method('PATCH')
                                        <button class="btn btn-outline-secondary" title="{{ __('app.toggle_status') }}" @disabled($user->id === auth()->id())>
                                            <i class="bi {{ $user->is_active ? 'bi-toggle-on' : 'bi-toggle-off' }}"></i>
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('users.destroy', $user) }}" data-confirm="{{ __('app.confirm_delete_user') }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="btn btn-outline-danger" title="{{ __('app.delete') }}" @disabled($user->id === auth()->id())>
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
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
        @if ($users->hasPages())
            <div class="card-footer bg-white">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</x-layouts.app>
