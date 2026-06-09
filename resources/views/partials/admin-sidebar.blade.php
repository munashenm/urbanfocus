@php $user = auth()->user(); @endphp
<nav class="admin-sidebar-nav">
    <a class="admin-nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
        <span class="admin-nav-icon">📊</span> Dashboard
    </a>

    @anypermission('products.view', 'products.create', 'products.edit')
        <div class="admin-nav-section">Catalog</div>
        @permission('products.view')
            <a class="admin-nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}" href="{{ route('admin.products.index') }}"><span class="admin-nav-icon">📦</span> Products</a>
            <a class="admin-nav-link {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ route('admin.categories.index') }}"><span class="admin-nav-icon">📁</span> Categories</a>
            @if(Route::has('admin.brands.index'))
                <a class="admin-nav-link {{ request()->routeIs('admin.brands.*') ? 'active' : '' }}" href="{{ route('admin.brands.index') }}"><span class="admin-nav-icon">🏷️</span> Brands</a>
            @endif
            <a class="admin-nav-link {{ request()->routeIs('admin.inventory.*') ? 'active' : '' }}" href="{{ route('admin.inventory.index') }}"><span class="admin-nav-icon">📋</span> Inventory</a>
        @endpermission
    @endanypermission

    @anypermission('orders.view', 'customers.view', 'rfqs.view', 'quotations.create')
        <div class="admin-nav-section">Sales</div>
        @permission('orders.view')
            <a class="admin-nav-link {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}" href="{{ route('admin.orders.index') }}"><span class="admin-nav-icon">🛒</span> Orders</a>
        @endpermission
        @permission('customers.view')
            <a class="admin-nav-link {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}" href="{{ route('admin.customers.index') }}"><span class="admin-nav-icon">👥</span> Customers</a>
        @endpermission
        @permission('rfqs.view')
            <a class="admin-nav-link {{ request()->routeIs('admin.quotes.*') ? 'active' : '' }}" href="{{ route('admin.quotes.index') }}"><span class="admin-nav-icon">📩</span> RFQs / Enquiries</a>
        @endpermission
        @anypermission('quotations.create', 'rfqs.view')
            @if(Route::has('admin.quotations.index'))
                <a class="admin-nav-link {{ request()->routeIs('admin.quotations.*') ? 'active' : '' }}" href="{{ route('admin.quotations.index') }}"><span class="admin-nav-icon">📄</span> Quotations</a>
            @endif
        @endanypermission
        @permission('products.edit')
            @if(Route::has('admin.coupons.index'))
                <a class="admin-nav-link {{ request()->routeIs('admin.coupons.*') ? 'active' : '' }}" href="{{ route('admin.coupons.index') }}"><span class="admin-nav-icon">🎟️</span> Coupons</a>
            @endif
        @endpermission
    @endanypermission

    @permission('settings.manage')
        <div class="admin-nav-section">Store</div>
        <a class="admin-nav-link {{ request()->routeIs('admin.settings.index') && request('tab') === 'shipping' ? 'active' : '' }}" href="{{ route('admin.settings.index', ['tab' => 'shipping']) }}"><span class="admin-nav-icon">🚚</span> Shipping</a>
        <a class="admin-nav-link {{ request()->routeIs('admin.settings.index') && request('tab') === 'payments' ? 'active' : '' }}" href="{{ route('admin.settings.index', ['tab' => 'payments']) }}"><span class="admin-nav-icon">💳</span> Payments</a>
    @endpermission

    @permission('reports.view')
        <a class="admin-nav-link {{ request()->routeIs('admin.reports.*') ? 'active' : '' }}" href="{{ route('admin.reports.index') }}"><span class="admin-nav-icon">📈</span> Reports</a>
    @endpermission

    @anypermission('users.manage', 'roles.manage', 'audit_logs.view')
        <div class="admin-nav-section">System</div>
        @permission('users.manage')
            <a class="admin-nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}"><span class="admin-nav-icon">🧑‍💼</span> Users</a>
        @endpermission
        @permission('roles.manage')
            <a class="admin-nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ route('admin.roles.index') }}"><span class="admin-nav-icon">🔐</span> Roles &amp; Permissions</a>
        @endpermission
        @permission('settings.manage')
            <a class="admin-nav-link {{ request()->routeIs('admin.settings.index') && !request('tab') ? 'active' : '' }}" href="{{ route('admin.settings.index') }}"><span class="admin-nav-icon">⚙️</span> Settings</a>
        @endpermission
        @permission('audit_logs.view')
            <a class="admin-nav-link {{ request()->routeIs('admin.audit-logs.*') ? 'active' : '' }}" href="{{ route('admin.audit-logs.index') }}"><span class="admin-nav-icon">📝</span> Audit Logs</a>
        @endpermission
    @endanypermission

    @if($user->hasPermission('products.edit') || $user->isSuperAdmin())
        <div class="admin-nav-section">Marketing</div>
        @if(Route::has('admin.catalog.index'))
            <a class="admin-nav-link {{ request()->routeIs('admin.catalog.*') ? 'active' : '' }}" href="{{ route('admin.catalog.index') }}"><span class="admin-nav-icon">🔗</span> Catalog &amp; Feeds</a>
        @endif
        @if(Route::has('admin.banners.index'))
            <a class="admin-nav-link {{ request()->routeIs('admin.banners.*') ? 'active' : '' }}" href="{{ route('admin.banners.index') }}"><span class="admin-nav-icon">🖼️</span> Banners</a>
        @endif
        @if(Route::has('admin.articles.index'))
            <a class="admin-nav-link {{ request()->routeIs('admin.articles.*') ? 'active' : '' }}" href="{{ route('admin.articles.index') }}"><span class="admin-nav-icon">✍️</span> Blog</a>
        @endif
        @if(Route::has('admin.social.index'))
            <a class="admin-nav-link {{ request()->routeIs('admin.social.*') ? 'active' : '' }}" href="{{ route('admin.social.index') }}"><span class="admin-nav-icon">📱</span> Social Media</a>
        @endif
    @endif
</nav>
