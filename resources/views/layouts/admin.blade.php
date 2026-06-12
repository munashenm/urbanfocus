<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Urban Focus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ public_asset_url('css/admin.css') }}" rel="stylesheet">
    <link rel="icon" href="{{ public_asset_url('favicon.svg') }}" type="image/svg+xml">
    @stack('head')
</head>
<body class="admin-body">
    <div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop" aria-hidden="true"></div>
    <div class="admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="admin-brand">
                <a href="{{ route('admin.dashboard') }}" class="admin-brand-link">
                    <img src="{{ public_asset_url('favicon.svg') }}" alt="" width="28" height="28">
                    <div>
                        <strong>Urban Focus</strong>
                        <small>Administration</small>
                    </div>
                </a>
            </div>
            @include('partials.admin-sidebar')
        </aside>

        <div class="admin-content">
            @include('partials.admin-topbar')
            <main class="admin-main">
                @include('admin.partials.rbac-setup-alert')
                @foreach (['success' => 'success', 'warning' => 'warning', 'error' => 'danger', 'info' => 'info'] as $key => $class)
                    @if(session($key))
                        <div class="alert alert-{{ $class }} alert-dismissible fade show" role="alert">
                            {{ session($key) }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif
                @endforeach
                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                @endif
                @yield('content')
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    (function () {
        const body = document.body;
        const btn = document.getElementById('admin-menu-btn');
        const backdrop = document.getElementById('admin-sidebar-backdrop');
        function closeNav() { body.classList.remove('admin-nav-open'); }
        function toggleNav() { body.classList.toggle('admin-nav-open'); }
        btn?.addEventListener('click', toggleNav);
        backdrop?.addEventListener('click', closeNav);
    })();
    </script>
    @stack('scripts')
</body>
</html>
