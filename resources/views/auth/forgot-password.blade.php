<x-layouts.guest :title="__('auth.forgot_password_title')">
    <p class="text-muted small mb-3">{{ __('auth.forgot_password_help') }}</p>

    <form method="POST" action="{{ route('password.email') }}" novalidate>
        @csrf

        <div class="mb-3">
            <label for="email" class="form-label">{{ __('auth.email') }}</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-control @error('email') is-invalid @enderror"
                   required autofocus>
            @error('email')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-primary w-100 mb-2">{{ __('auth.send_reset_link') }}</button>
        <a href="{{ route('login') }}" class="btn btn-link w-100">{{ __('auth.back_to_login') }}</a>
    </form>
</x-layouts.guest>
