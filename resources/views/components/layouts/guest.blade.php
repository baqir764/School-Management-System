<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('app.available_locales.'.app()->getLocale().'.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app.app_name') }} — {{ \App\Models\Setting::current()->school_name }}</title>
    @vite(app()->getLocale() && in_array(config('app.available_locales.'.app()->getLocale().'.dir'), ['rtl'])
        ? ['resources/css/app-rtl.scss', 'resources/js/app.js']
        : ['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="sms-auth-wrapper p-3">
        <div class="card sms-auth-card">
            <div class="card-body p-4 p-md-5">
                <div class="text-center mb-4">
                    @if ($logo = \App\Models\Setting::current()->logo_path)
                        <img src="{{ Storage::url($logo) }}" alt="{{ \App\Models\Setting::current()->school_name }}" style="height:56px" class="mb-2">
                    @endif
                    <h1 class="h4 fw-bold mb-0">{{ \App\Models\Setting::current()->school_name }}</h1>
                    <p class="text-muted small mb-0">{{ __('app.app_name') }}</p>
                </div>

                <x-alert />

                {{ $slot }}
            </div>
        </div>
    </div>
</body>
</html>
