<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center py-2 small">
        <div class="d-none d-md-block">
            <span class="text-white-50 me-3">Free shipping on orders over R {{ number_format(config('shipping.free_threshold'), 0) }}</span>
            <a href="tel:0875501813" class="text-white text-decoration-none me-3">087 550 1813</a>
            <a href="mailto:sales@urbanfocus.co.za" class="text-white text-decoration-none">sales@urbanfocus.co.za</a>
        </div>
        <div class="ms-auto">
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="text-white text-decoration-none me-3">Admin</a>
                @endif
                <a href="{{ route('account.dashboard') }}" class="text-white text-decoration-none me-3">My Account</a>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">@csrf<button class="btn btn-link btn-sm text-white p-0">Logout</button></form>
            @else
                <a href="{{ route('login') }}" class="text-white text-decoration-none me-3">Login</a>
                <a href="{{ route('register') }}" class="text-white text-decoration-none">Register</a>
            @endauth
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm site-header">
    <div class="container">
        <a class="site-logo navbar-brand py-0" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="Urban Focus — IT Products & Software" width="200" height="42">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <div class="search-wrap mx-lg-3 flex-grow-1 my-3 my-lg-0 position-relative">
                <form action="{{ route('shop.index') }}" method="GET" role="search" id="searchForm">
                    <div class="input-group">
                        <input class="form-control search-input" type="search" name="q" id="searchInput"
                               placeholder="Search by name, brand, SKU, model..." value="{{ request('q') }}"
                               autocomplete="off" aria-label="Search products"
                               data-suggest-url="{{ route('search.suggest') }}"
                               data-placeholder-img="{{ asset('images/product-placeholder.svg') }}">
                        <button class="btn btn-primary px-3" type="submit" aria-label="Search">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
                        </button>
                    </div>
                </form>
                <div id="searchSuggestions" class="search-suggestions d-none"></div>
            </div>
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                <li class="nav-item dropdown mega-dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('shop.*') || request()->routeIs('categories.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown" data-bs-auto-close="outside">Shop</a>
                    <div class="dropdown-menu mega-menu shadow border-0 p-0">
                        <div class="container py-4">
                            <div class="row g-4">
                                @foreach($megaMenuCategories ?? [] as $col)
                                    <div class="col-6 col-md-4 col-lg-3">
                                        <a href="{{ $col['url'] }}" class="mega-menu-heading">{{ $col['label'] }}</a>
                                        @if(count($col['children']))
                                            <ul class="list-unstyled mega-menu-links mt-2">
                                                @foreach(array_slice($col['children'], 0, 5) as $child)
                                                    <li><a href="{{ $child['url'] }}">{{ $child['name'] }}</a></li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <div class="border-top mt-3 pt-3 d-flex flex-wrap gap-2">
                                <a href="{{ route('shop.index') }}" class="btn btn-sm btn-primary">All Products</a>
                                <a href="{{ route('b2b.quote') }}" class="btn btn-sm btn-outline-primary">Request Quote</a>
                                <a href="{{ route('b2b.rfq') }}" class="btn btn-sm btn-outline-secondary">Upload RFQ</a>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('b2b.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">Business</a>
                    <ul class="dropdown-menu shadow border-0">
                        <li><a class="dropdown-item" href="{{ route('b2b.quote') }}">Request a Quote</a></li>
                        <li><a class="dropdown-item" href="{{ route('b2b.rfq') }}">Upload RFQ</a></li>
                        <li><a class="dropdown-item" href="{{ route('b2b.procurement') }}">Corporate Procurement</a></li>
                        <li><a class="dropdown-item" href="{{ route('b2b.source') }}">Source a Product</a></li>
                    </ul>
                </li>
                <li class="nav-item d-none d-lg-block"><a class="nav-link {{ request()->routeIs('blog.*') ? 'active' : '' }}" href="{{ route('blog.index') }}">Blog</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">Contact</a></li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-outline-primary position-relative px-3" href="{{ route('cart.index') }}">
                        Cart
                        @php $cartCount = app(\App\Services\CartService::class)->count(); @endphp
                        @if($cartCount > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-accent">{{ $cartCount }}</span>
                        @endif
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="mobile-search-bar d-lg-none border-bottom bg-white sticky-top" style="top:56px;z-index:1025">
    <div class="container py-2">
        <form action="{{ route('shop.index') }}" method="GET" role="search">
            <input class="form-control form-control-sm search-input" type="search" name="q" placeholder="Search products, SKU, brands..." value="{{ request('q') }}" aria-label="Search">
        </form>
    </div>
</div>
