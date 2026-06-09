@php
    $user = auth()->user();
    $newEnquiries = \App\Models\Quote::where('status', 'new')->count();
    $pendingOrders = \App\Models\Order::whereIn('status', ['pending', 'pending_payment', 'processing'])->count();
@endphp
<header class="admin-topbar">
    <div class="admin-topbar-left">
        <button type="button" class="admin-menu-btn" id="admin-menu-btn" aria-label="Open menu">
            <span></span><span></span><span></span>
        </button>
        <h1 class="admin-page-title">@yield('page_title', 'Dashboard')</h1>
    </div>

    <div class="admin-topbar-center d-none d-lg-block">
        <form action="{{ route('admin.products.index') }}" method="GET" class="admin-search-form">
            <input type="search" name="q" class="form-control form-control-sm" placeholder="Search products, orders, customers…" value="{{ request('q') }}">
        </form>
    </div>

    <div class="admin-topbar-right">
        <div class="dropdown">
            <button class="admin-icon-btn" type="button" data-bs-toggle="dropdown" aria-label="Notifications">
                🔔
                @if(($newEnquiries + $pendingOrders) > 0)
                    <span class="admin-badge">{{ min($newEnquiries + $pendingOrders, 99) }}</span>
                @endif
            </button>
            <ul class="dropdown-menu dropdown-menu-end admin-dropdown">
                @if($pendingOrders > 0)
                    <li><a class="dropdown-item" href="{{ route('admin.orders.index') }}?status=pending">{{ $pendingOrders }} pending order(s)</a></li>
                @endif
                @if($newEnquiries > 0)
                    <li><a class="dropdown-item" href="{{ route('admin.quotes.index') }}?status=new">{{ $newEnquiries }} new RFQ(s)</a></li>
                @endif
                @if(($newEnquiries + $pendingOrders) === 0)
                    <li><span class="dropdown-item-text text-muted">No new notifications</span></li>
                @endif
            </ul>
        </div>

        <div class="dropdown">
            <button class="admin-profile-btn dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <span class="admin-avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</span>
                <span class="d-none d-md-inline">{{ $user->name }}</span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end admin-dropdown">
                <li class="dropdown-header">{{ $user->roleLabels() }}</li>
                <li><a class="dropdown-item" href="{{ route('account.profile.edit') }}">My Profile</a></li>
                @permission('settings.manage')
                    <li><a class="dropdown-item" href="{{ route('admin.settings.index') }}">Settings</a></li>
                @endpermission
                <li><a class="dropdown-item" href="{{ route('home') }}" target="_blank">View Store</a></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="dropdown-item text-danger">Logout</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>
</header>
