<!DOCTYPE html>
<html lang="en-ZA">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <meta name="description" content="@yield('meta_description', config('seo.defaults.description'))">
    @hasSection('meta_robots')
        <meta name="robots" content="@yield('meta_robots')">
    @endif
    @hasSection('meta_keywords')
        <meta name="keywords" content="@yield('meta_keywords')">
    @else
        <meta name="keywords" content="{{ config('seo.defaults.keywords') }}">
    @endif
    @if(config('seo.verification.google'))
        <meta name="google-site-verification" content="{{ config('seo.verification.google') }}">
    @endif
    @if(config('seo.verification.bing'))
        <meta name="msvalidate.01" content="{{ config('seo.verification.bing') }}">
    @endif
    <link rel="canonical" href="@yield('canonical', url()->current())">
    <meta property="og:site_name" content="Urban Focus">
    <meta property="og:title" content="@yield('og_title', trim($__env->yieldContent('title')))">
    <meta property="og:description" content="@yield('og_description', trim($__env->yieldContent('meta_description')))">
    <meta property="og:url" content="@yield('canonical', url()->current())">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="{{ config('seo.defaults.locale', 'en_ZA') }}">
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
    @else
        <meta property="og:image" content="{{ asset('images/logo-stacked.png') }}">
    @endif
    <meta name="twitter:card" content="@yield('twitter_card', 'summary')">
    <meta name="twitter:title" content="@yield('og_title', trim($__env->yieldContent('title')))">
    <meta name="twitter:description" content="@yield('og_description', trim($__env->yieldContent('meta_description')))">
    @hasSection('og_image')
        <meta name="twitter:image" content="@yield('og_image')">
    @else
        <meta name="twitter:image" content="{{ asset('images/logo-stacked.png') }}">
    @endif
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" sizes="32x32">
    <link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">
    <meta name="theme-color" content="#0a1628">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" href="{{ asset('css/app.css') }}" as="style">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    @stack('head')
    @stack('schema')
</head>
<body class="d-flex flex-column min-vh-100{{ app(\App\Services\CompareService::class)->count() && ! request()->routeIs('compare.index') ? ' has-compare-bar' : '' }}">
    @include('partials.analytics')
    <a href="#main-content" class="visually-hidden-focusable skip-link">Skip to content</a>
    @include('partials.header')

    @if(session('success'))
        <div class="container mt-3"><div class="alert alert-success alert-dismissible fade show mb-0" role="alert">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>
    @endif
    @if(session('error'))
        <div class="container mt-3"><div class="alert alert-danger alert-dismissible fade show mb-0" role="alert">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>
    @endif
    @if(session('warning'))
        <div class="container mt-3"><div class="alert alert-warning alert-dismissible fade show mb-0" role="alert">{{ session('warning') }}<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div></div>
    @endif

    <main id="main-content" class="flex-grow-1">@yield('content')</main>

    @include('partials.footer')
    @include('partials.compare-bar')
    @include('partials.whatsapp-button')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="{{ asset('js/search.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
