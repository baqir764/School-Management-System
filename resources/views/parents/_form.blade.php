@php
    $editing = isset($parent);
@endphp

<div class="row g-3">
    <div class="col-md-3 text-center">
        <div class="mb-2">
            @if ($editing && $parent->photo_path)
                <img src="{{ Storage::url($parent->photo_path) }}" alt="" class="img-thumbnail" style="max-height:120px">
            @else
                <div class="border rounded d-flex align-items-center justify-content-center text-muted mx-auto" style="height:120px;width:120px">
                    <i class="bi bi-person fs-1"></i>
                </div>
            @endif
        </div>
        <input type="file" name="photo" class="form-control form-control-sm @error('photo') is-invalid @enderror" accept="image/*">
        @error('photo')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
    </div>

    <div class="col-md-9">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_first_name') }}</label>
                <input type="text" name="first_name" value="{{ old('first_name', $editing ? $parent->first_name : '') }}" class="form-control @error('first_name') is-invalid @enderror" required autofocus>
                @error('first_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_last_name') }}</label>
                <input type="text" name="last_name" value="{{ old('last_name', $editing ? $parent->last_name : '') }}" class="form-control @error('last_name') is-invalid @enderror" required>
                @error('last_name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_national_id') }}</label>
                <input type="text" name="national_id" value="{{ old('national_id', $editing ? $parent->national_id : '') }}" class="form-control">
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_phone') }}</label>
                <input type="text" name="phone" value="{{ old('phone', $editing ? $parent->phone : '') }}" class="form-control @error('phone') is-invalid @enderror" required>
                @error('phone')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_email') }}</label>
                <input type="email" name="email" value="{{ old('email', $editing ? $parent->email : '') }}" class="form-control @error('email') is-invalid @enderror">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">{{ __('app.field_occupation') }}</label>
                <input type="text" name="occupation" value="{{ old('occupation', $editing ? $parent->occupation : '') }}" class="form-control">
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <label class="form-label">{{ __('app.field_address') }}</label>
        <input type="text" name="address" value="{{ old('address', $editing ? $parent->address : '') }}" class="form-control">
    </div>
    <div class="col-md-4">
        <label class="form-label">{{ __('app.field_link_user_account') }}</label>
        <select name="user_id" class="form-select @error('user_id') is-invalid @enderror">
            <option value="">{{ __('app.none') }}</option>
            @foreach ($linkableUsers as $user)
                <option value="{{ $user->id }}" @selected(old('user_id', $editing ? $parent->user_id : '') == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
            @endforeach
        </select>
        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
</div>

<div class="mt-4 d-flex gap-2">
    <button type="submit" class="btn btn-primary">{{ $editing ? __('app.save_changes') : __('app.create_parent') }}</button>
    <a href="{{ $editing ? route('parents.show', $parent) : route('parents.index') }}" class="btn btn-outline-secondary">{{ __('app.cancel') }}</a>
</div>
