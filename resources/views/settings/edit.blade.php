<x-layouts.app :title="__('app.nav_school_settings')">
    <div class="card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" novalidate>
                @csrf
                @method('PUT')

                <div class="row g-3">
                    <div class="col-md-4 text-center">
                        <div class="mb-2">
                            @if ($setting->logo_path)
                                <img src="{{ Storage::url($setting->logo_path) }}" alt="" class="img-thumbnail" style="max-height:140px">
                            @else
                                <div class="border rounded d-flex align-items-center justify-content-center text-muted" style="height:140px">
                                    <i class="bi bi-image fs-1"></i>
                                </div>
                            @endif
                        </div>
                        <input type="file" name="logo" class="form-control form-control-sm @error('logo') is-invalid @enderror" accept="image/*">
                        @error('logo')
                            <div class="invalid-feedback d-block">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-8">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_school_name') }}</label>
                                <input type="text" name="school_name" value="{{ old('school_name', $setting->school_name) }}" class="form-control @error('school_name') is-invalid @enderror" required>
                                @error('school_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_registration_number') }}</label>
                                <input type="text" name="registration_number" value="{{ old('registration_number', $setting->registration_number) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_principal_name') }}</label>
                                <input type="text" name="principal_name" value="{{ old('principal_name', $setting->principal_name) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_phone') }}</label>
                                <input type="text" name="phone" value="{{ old('phone', $setting->phone) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_email') }}</label>
                                <input type="email" name="email" value="{{ old('email', $setting->email) }}" class="form-control @error('email') is-invalid @enderror">
                                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_website') }}</label>
                                <input type="text" name="website" value="{{ old('website', $setting->website) }}" class="form-control @error('website') is-invalid @enderror">
                                @error('website')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_province') }}</label>
                                <input type="text" name="province" value="{{ old('province', $setting->province) }}" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">{{ __('app.field_district') }}</label>
                                <input type="text" name="district" value="{{ old('district', $setting->district) }}" class="form-control">
                            </div>
                            <div class="col-12">
                                <label class="form-label">{{ __('app.field_address') }}</label>
                                <input type="text" name="address" value="{{ old('address', $setting->address) }}" class="form-control">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('app.field_currency') }}</label>
                                <input type="text" name="currency" value="{{ old('currency', $setting->currency) }}" maxlength="3" class="form-control @error('currency') is-invalid @enderror" required>
                                @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('app.field_timezone') }}</label>
                                <input type="text" name="timezone" value="{{ old('timezone', $setting->timezone) }}" class="form-control @error('timezone') is-invalid @enderror" required>
                                @error('timezone')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('app.field_start_time') }}</label>
                                <input type="time" name="school_start_time" value="{{ old('school_start_time', $setting->school_start_time ? substr($setting->school_start_time, 0, 5) : '') }}" class="form-control @error('school_start_time') is-invalid @enderror">
                                <div class="form-text">{{ __('app.field_start_time_help') }}</div>
                                @error('school_start_time')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">{{ __('app.field_default_language') }}</label>
                                <select name="default_locale" class="form-select">
                                    @foreach (config('app.available_locales') as $code => $meta)
                                        <option value="{{ $code }}" @selected(old('default_locale', $setting->default_locale) === $code)>{{ $meta['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">{{ __('app.save_changes') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-body p-4">
            <h2 class="h5 mb-1">{{ __('app.nav_backup') }}</h2>
            <p class="text-muted mb-3">{{ __('app.backup_description') }}</p>
            <a href="{{ route('settings.backup') }}" class="btn btn-outline-primary">
                <i class="bi bi-download me-1"></i>{{ __('app.download_backup') }}
            </a>
        </div>
    </div>

    @can('settings.restore')
        <div class="card mt-3 border-danger">
            <div class="card-body p-4">
                <h2 class="h5 mb-1 text-danger">{{ __('app.danger_zone_title') }}</h2>
                <p class="text-muted mb-4">{{ __('app.danger_zone_description') }}</p>

                <div class="row g-4">
                    <div class="col-md-6">
                        <h3 class="h6">{{ __('app.restore_backup') }}</h3>
                        <p class="text-muted small mb-3">{{ __('app.restore_backup_description') }}</p>

                        <form method="POST" action="{{ route('settings.backup.restore') }}" enctype="multipart/form-data" data-confirm="{{ __('app.confirm_restore_backup') }}" novalidate>
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">{{ __('app.field_backup_file') }}</label>
                                <input type="file" name="backup_file" accept=".zip" class="form-control form-control-sm @error('backup_file') is-invalid @enderror" required>
                                @error('backup_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-2">
                                <label class="form-label">{{ __('app.field_type_restore_to_confirm') }}</label>
                                <input type="text" name="confirmation_phrase" class="form-control form-control-sm @error('confirmation_phrase') is-invalid @enderror" autocomplete="off" required>
                                @error('confirmation_phrase')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('app.current_password') }}</label>
                                <input type="password" name="current_password" class="form-control form-control-sm @error('current_password') is-invalid @enderror" required>
                                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-upload me-1"></i>{{ __('app.restore_backup') }}
                            </button>
                        </form>
                    </div>

                    <div class="col-md-6">
                        <h3 class="h6">{{ __('app.reset_database') }}</h3>
                        <p class="text-muted small mb-3">{{ __('app.reset_database_description') }}</p>

                        <form method="POST" action="{{ route('settings.database.reset') }}" data-confirm="{{ __('app.confirm_reset_database') }}" novalidate>
                            @csrf
                            <div class="mb-2">
                                <label class="form-label">{{ __('app.field_type_reset_to_confirm') }}</label>
                                <input type="text" name="confirmation_phrase" class="form-control form-control-sm @error('confirmation_phrase') is-invalid @enderror" autocomplete="off" required>
                                @error('confirmation_phrase')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label">{{ __('app.current_password') }}</label>
                                <input type="password" name="current_password" class="form-control form-control-sm @error('current_password') is-invalid @enderror" required>
                                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="bi bi-exclamation-triangle me-1"></i>{{ __('app.reset_database') }}
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan
</x-layouts.app>
