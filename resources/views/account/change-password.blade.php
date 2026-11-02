<x-layouts.app :title="__('app.nav_change_password')">
    <div class="row justify-content-center">
        <div class="col-lg-6">
            @if (auth()->user()->must_change_password)
                <div class="alert alert-warning">{{ __('app.must_change_password_notice') }}</div>
            @endif

            <div class="card">
                <div class="card-body p-4">
                    <form method="POST" action="{{ route('account.password.update') }}" novalidate>
                        @csrf
                        @method('PUT')

                        <div class="mb-3">
                            <label for="current_password" class="form-label">{{ __('app.current_password') }}</label>
                            <input id="current_password" type="password" name="current_password"
                                   class="form-control @error('current_password') is-invalid @enderror" required>
                            @error('current_password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">{{ __('app.new_password') }}</label>
                            <input id="password" type="password" name="password"
                                   class="form-control @error('password') is-invalid @enderror" required>
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="form-text">{{ __('app.password_requirements') }}</div>
                        </div>

                        <div class="mb-4">
                            <label for="password_confirmation" class="form-label">{{ __('app.confirm_new_password') }}</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" class="form-control" required>
                        </div>

                        <button type="submit" class="btn btn-primary">{{ __('app.update_password') }}</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app>
