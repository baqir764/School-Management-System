@props(['code', 'heading', 'message'])
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $heading }}</title>
    @vite(['resources/css/app.scss'])
</head>
<body class="d-flex align-items-center justify-content-center" style="min-height:100vh">
    <div class="text-center p-4">
        <div class="display-1 fw-bold text-primary">{{ $code }}</div>
        <h1 class="h4 mb-2">{{ $heading }}</h1>
        <p class="text-muted mb-4">{{ $message }}</p>
        <a href="{{ url('/') }}" class="btn btn-primary">{{ __('app.back_to_home') }}</a>
    </div>
</body>
</html>
