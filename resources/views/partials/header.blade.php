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

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top shadow-sm">
    <div class="container">
        <a class="site-logo navbar-brand py-0" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="Urban Focus — IT Products & Software" width="200" height="42">
        </a>
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <form class="d-flex mx-lg-4 flex-grow-1 my-3 my-lg-0" action="{{ route('shop.index') }}" method="GET" role="search">
                <input class="form-control search-input" type="search" name="q" placeholder="Search products, brands, SKU..." value="{{ request('q') }}" aria-label="Search products">
                <button class="btn btn-primary ms-2 px-3" type="submit" aria-label="Search">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" viewBox="0 0 16 16"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z"/></svg>
                </button>
            </form>
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">Home</a></li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle {{ request()->routeIs('shop.*') || request()->routeIs('categories.*') ? 'active' : '' }}" href="#" data-bs-toggle="dropdown">Shop</a>
                    <ul class="dropdown-menu shadow border-0">
                        <li><a class="dropdown-item" href="{{ route('shop.index') }}">All Products</a></li>
                        @foreach($navCategories ?? [] as $cat)
                            <li><a class="dropdown-item" href="{{ route('categories.show', $cat) }}">{{ $cat->name }}</a></li>
                        @endforeach
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('about') ? 'active' : '' }}" href="{{ route('about') }}">About</a></li>
                <li class="nav-item"><a class="nav-link {{ request()->routeIs('contact') ? 'active' : '' }}" href="{{ route('contact') }}">Contact</a></li>
                <li class="nav-item ms-lg-2">
                    <a class="btn btn-outline-primary position-relative px-3" href="{{ route('cart.index') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor" class="me-1" viewBox="0 0 16 16"><path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4zm-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zm7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>
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
