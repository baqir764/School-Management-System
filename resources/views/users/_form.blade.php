@php
    $editing = isset($user);
    $selectedRoles = old('roles', $editing ? $user->roles->pluck('name')->toArray() : []);
@endphp

<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_full_name') }}</label>
        <input type="text" name="name" value="{{ old('name', $editing ? $user->name : '') }}" class="form-control @error('name') is-invalid @enderror" required autofocus>
        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_email') }}</label>
        <input type="email" name="email" value="{{ old('email', $editing ? $user->email : '') }}" class="form-control @error('email') is-invalid @enderror" required>
        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_phone') }}</label>
        <input type="text" name="phone" value="{{ old('phone', $editing ? $user->phone : '') }}" class="form-control">
    </div>

    <div class="col-md-6 d-flex align-items-end">
        <div class="form-check form-switch mb-2">
            <input type="hidden" name="is_active" value="0">
            <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" @checked(old('is_active', $editing ? $user->is_active : true))>
            <label class="form-check-label" for="is_active">{{ __('app.field_active') }}</label>
        </div>
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_password') }} @if ($editing)<span class="text-muted small">({{ __('app.leave_blank_to_keep') }})</span>@endif</label>
        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" {{ $editing ? '' : 'required' }}>
        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-6">
        <label class="form-label">{{ __('app.field_confirm_password') }}</label>
        <input type="password" name="password_confirmation" class="form-control">
    </div>

    <div class="col-12">
        <label class="form-label d-block">{{ __('app.field_roles') }}</label>
        <div class="row row-cols-2 row-cols-md-3 g-2">
            @foreach ($roles as $role)
                <div class="col">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="roles[]" value="{{ $role->name }}" id="role-{{ $role->id }}"
                               @checked(in_array($role->name, $selectedRoles))>
                        <label class="form-check-label" for="role-{{ $role->id }}">{{ $role->name }}</label>
                    </div>
                </div>
            @endforeach
        </div>
        @error('roles')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_user') }}</button>
    <a href="{{ route('users.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
