@props(['title' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('app.available_locales.'.app()->getLocale().'.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('app.nav_dashboard') }} — {{ \App\Models\Setting::current()->school_name }}</title>
    @vite(config('app.available_locales.'.app()->getLocale().'.dir') === 'rtl'
        ? ['resources/css/app-rtl.scss', 'resources/js/app.js']
        : ['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="sms-sidebar-backdrop" id="smsSidebarBackdrop" hidden></div>
    <div class="d-flex">
        <x-sidebar />

        <div class="sms-page">
            <div class="sms-page-inner">
                <x-topbar :title="$title ?? null">
                    @isset($breadcrumb)
                        <x-slot:breadcrumb>{{ $breadcrumb }}</x-slot:breadcrumb>
                    @endisset
                </x-topbar>

                <main class="p-3 p-md-4">
                    <x-alert />

                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>

    <div class="toast-container position-fixed bottom-0 end-0 p-3">
        @if (session('status'))
            <div class="toast align-items-center text-bg-success border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body">{{ session('status') }}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        @endif
    </div>
</body>
</html>
