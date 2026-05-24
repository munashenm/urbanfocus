<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - Urban Focus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/admin.css') }}" rel="stylesheet">
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}">
</head>
<body class="admin-body">
    <div class="d-flex">
        <aside class="admin-sidebar">
            <div class="admin-brand">
                <a href="{{ route('admin.dashboard') }}" class="d-flex align-items-center gap-2 text-white text-decoration-none">
                    <img src="{{ asset('images/favicon.png') }}" alt="" width="32" height="32" style="border-radius:6px">
                    <span>Urban Focus</span>
                </a>
            </div>
            <nav class="nav flex-column">
                <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
                <a class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}">Products</a>
                <a class="nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}">Categories</a>
                <a class="nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}">Orders</a>
                @if(Route::has('admin.users.index'))
                <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">Users</a>
                @endif
                @if(Route::has('admin.catalog.index'))
                <a class="nav-link {{ request()->routeIs('admin.catalog.*') || request()->routeIs('admin.import.*') ? 'active' : '' }}" href="{{ route('admin.catalog.index') }}">Catalog &amp; Feeds</a>
                @elseif(Route::has('admin.import.index'))
                <a class="nav-link {{ request()->routeIs('admin.import.*') ? 'active' : '' }}" href="{{ route('admin.import.index') }}">Import CSV</a>
                @endif
                @if(Route::has('admin.brands.index'))
                <a class="nav-link {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}" href="{{ route('admin.brands.index') }}">Brands</a>
                <a class="nav-link {{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}" href="{{ route('admin.quotes.index') }}">Quotes &amp; RFQs</a>
                <a class="nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}" href="{{ route('admin.banners.index') }}">Banners</a>
                <a class="nav-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}">Coupons</a>
                <a class="nav-link {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}">Blog</a>
                <a class="nav-link {{ request()->routeIs('admin.social.*') ? 'active' : '' }}" href="{{ route('admin.social.index') }}">Social Media</a>
                @endif
                <hr>
                <a class="nav-link {{ request()->routeIs('account.profile.*') ? 'active' : '' }}" href="{{ route('account.profile.edit') }}">My Profile</a>
                <a class="nav-link" href="{{ route('home') }}" target="_blank">View Store</a>
                <form action="{{ route('logout') }}" method="POST">@csrf<button class="nav-link border-0 bg-transparent text-start w-100">Logout</button></form>
            </nav>
        </aside>
        <div class="admin-content flex-grow-1">
            <header class="admin-topbar d-flex justify-content-between align-items-center">
                <h1 class="h4 mb-0">@yield('page_title', 'Dashboard')</h1>
                <div class="d-flex align-items-center gap-3">
                    <a href="{{ route('account.profile.edit') }}" class="small text-decoration-none">{{ auth()->user()->name }}</a>
                </div>
            </header>
            <div class="p-4">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('warning'))<div class="alert alert-warning">{{ session('warning') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
