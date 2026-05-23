<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', 'Urban Focus - South African supplier of IT products, hardware and software. Fast delivery, competitive pricing.')">
    @hasSection('meta_keywords')
        <meta name="keywords" content="@yield('meta_keywords')">
    @endif
    @if(config('app.google_site_verification') ?? env('GOOGLE_SITE_VERIFICATION'))
        <meta name="google-site-verification" content="{{ env('GOOGLE_SITE_VERIFICATION') }}">
    @endif
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('head')
    @stack('schema')
</head>
<body>
    @include('partials.header')

    @if(session('success'))
        <div class="container mt-3"><div class="alert alert-success mb-0">{{ session('success') }}</div></div>
    @endif
    @if(session('error'))
        <div class="container mt-3"><div class="alert alert-danger mb-0">{{ session('error') }}</div></div>
    @endif
    @if(session('warning'))
        <div class="container mt-3"><div class="alert alert-warning mb-0">{{ session('warning') }}</div></div>
    @endif

    <main>@yield('content')</main>

    @include('partials.footer')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    @stack('scripts')
</body>
</html>
