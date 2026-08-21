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
        @php
            $cartCount = app(\App\Services\CartService::class)->count();
            $wishlistCount = app(\App\Services\WishlistService::class)->count();
            $compareCount = app(\App\Services\CompareService::class)->count();
        @endphp
        <div class="header-actions d-flex align-items-center gap-1 ms-auto order-lg-3">
            <a class="header-icon-btn {{ request()->routeIs('wishlist.*') ? 'is-active' : '' }}" href="{{ route('wishlist.index') }}" aria-label="Wishlist{{ $wishlistCount ? ' ('.$wishlistCount.' items)' : '' }}" title="Wishlist">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.6" viewBox="0 0 16 16" aria-hidden="true"><path d="m8 13.5-5.2-5.05A3.3 3.3 0 1 1 8 3.55a3.3 3.3 0 1 1 5.2 4.9L8 13.5z"/></svg>
                @if($wishlistCount > 0)
                    <span class="header-icon-badge">{{ $wishlistCount }}</span>
                @endif
            </a>
            <a class="header-icon-btn {{ request()->routeIs('compare.*') ? 'is-active' : '' }}" href="{{ route('compare.index') }}" aria-label="Compare products{{ $compareCount ? ' ('.$compareCount.' items)' : '' }}" title="Compare">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2v12H2V2h2zm10 0v12h-2V2h2zM9.5 4v8H6.5V4h3z"/></svg>
                @if($compareCount > 0)
                    <span class="header-icon-badge">{{ $compareCount }}</span>
                @endif
            </a>
            <a class="header-icon-btn header-icon-btn--cart {{ request()->routeIs('cart.*') ? 'is-active' : '' }}" href="{{ route('cart.index') }}" aria-label="Cart{{ $cartCount ? ' ('.$cartCount.' items)' : '' }}" title="Cart">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .49.598l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/></svg>
                @if($cartCount > 0)
                    <span class="header-icon-badge">{{ $cartCount }}</span>
                @endif
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>
        <div class="collapse navbar-collapse order-lg-2 flex-grow-1" id="mainNav">
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
                <li class="nav-item d-lg-none w-100">
                    <div class="accordion accordion-flush mobile-shop-accordion" id="mobileShopAccordion">
                        <div class="accordion-item border-0">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed py-2 px-0 bg-transparent shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#mobileShopCategories">
                                    Shop by category
                                </button>
                            </h2>
                            <div id="mobileShopCategories" class="accordion-collapse collapse" data-bs-parent="#mobileShopAccordion">
                                <div class="accordion-body px-0 pt-0">
                                    @foreach($megaMenuCategories ?? [] as $col)
                                        <div class="mb-2">
                                            <a href="{{ $col['url'] }}" class="fw-semibold text-decoration-none d-block mb-1">{{ $col['label'] }}</a>
                                            @if(count($col['children']))
                                                <ul class="list-unstyled ps-2 mb-0 small">
                                                    @foreach($col['children'] as $child)
                                                        <li class="mb-1"><a href="{{ $child['url'] }}" class="text-muted text-decoration-none">{{ $child['name'] }}</a></li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                </li>
                <li class="nav-item d-none d-lg-block"><a class="nav-link {{ request()->routeIs('blog.*') ? 'active' : '' }}" href="{{ route('blog.index') }}">Blog</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">Contact</a></li>
            </ul>
        </div>
    </div>
</nav>

<div class="mobile-search-bar d-lg-none border-bottom bg-white sticky-top" style="top:56px;z-index:1025">
    <div class="container py-2">
        <form action="{{ route('shop.index') }}" method="GET" role="search" id="mobileSearchForm">
            <div class="position-relative">
                <input class="form-control form-control-sm search-input" type="search" name="q" id="mobileSearchInput"
                       placeholder="Search products, SKU, brands..." value="{{ request('q') }}" aria-label="Search"
                       autocomplete="off"
                       data-suggest-url="{{ route('search.suggest') }}"
                       data-placeholder-img="{{ asset('images/product-placeholder.svg') }}">
                <div id="mobileSearchSuggestions" class="search-suggestions d-none"></div>
            </div>
        </form>
    </div>
</div>
