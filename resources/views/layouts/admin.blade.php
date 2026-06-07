<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Urban Focus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png" sizes="32x32">
</head>
<body class="admin-body">
    <div class="admin-sidebar-backdrop" id="admin-sidebar-backdrop" aria-hidden="true"></div>
    <div class="d-flex admin-shell">
        <aside class="admin-sidebar" id="admin-sidebar">
            <div class="admin-brand">
                <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                    <img src="{{ asset('favicon.svg') }}" alt="" width="32" height="32">
                    <span>Urban Focus</span>
                </a>
            </div>
            <nav class="nav flex-column pb-3">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>

                <div class="admin-nav-section">Catalog</div>
                <a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
                <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">Categories</a>
                @if(Route::has('admin.brands.index'))
                <a class="nav-link {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}" href="{{ route('admin.brands.index') }}">Brands</a>
                @endif
                @if(Route::has('admin.catalog.index'))
                <a class="nav-link {{ request()->routeIs('admin.catalog.*') || request()->routeIs('admin.import.*') ? 'active' : '' }}" href="{{ route('admin.catalog.index') }}">Catalog &amp; Feeds</a>
                @endif

                <div class="admin-nav-section">Sales</div>
                <a class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">Orders</a>
                @if(Route::has('admin.quotations.*'))
                <a class="nav-link {{ request()->routeIs('admin.quotations.*') ? 'active' : '' }}" href="{{ route('admin.quotations.index') }}">Quotations</a>
                @endif
                <a class="nav-link {{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}" href="{{ route('admin.quotes.index') }}">Enquiries (RFQ)</a>
                @if(Route::has('admin.coupons.index'))
                <a class="nav-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}">Coupons</a>
                @endif

                @if(Route::has('admin.users.index'))
                <div class="admin-nav-section">People</div>
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>
                @endif

                @if(Route::has('admin.banners.index'))
                <div class="admin-nav-section">Marketing</div>
                <a class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}" href="{{ route('admin.banners.index') }}">Banners</a>
                <a class="nav-link {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}">Blog</a>
                <a class="nav-link {{ request()->routeIs('admin.blog-strategy.*') ? 'active' : '' }}" href="{{ route('admin.blog-strategy.index') }}">Content Strategy</a>
                <a class="nav-link {{ request()->routeIs('admin.social.*') ? 'active' : '' }}" href="{{ route('admin.social.index') }}">Social Media</a>
                @endif

                <hr>
                <a class="nav-link {{ request()->routeIs('account.profile.*') ? 'active' : '' }}" href="{{ route('account.profile.edit') }}">My Profile</a>
                <a class="nav-link" href="{{ route('home') }}" target="_blank">View Store</a>
                <form action="{{ route('logout') }}" method="POST">@csrf<button class="nav-link border-0 bg-transparent text-start w-100">Logout</button></form>
            </nav>
        </aside>
        <div class="admin-content flex-grow-1">
            <header class="admin-topbar d-flex justify-content-between align-items-center gap-2">
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="admin-menu-btn" id="admin-menu-btn" aria-label="Open menu">☰</button>
                    <h1 class="h5 mb-0">@yield('page_title', 'Dashboard')</h1>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('account.profile.edit') }}" class="small text-decoration-none text-muted">{{ auth()->user()->name }}</a>
                </div>
            </header>
            <div class="admin-main">
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
            </div>
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
</body>
</html>
