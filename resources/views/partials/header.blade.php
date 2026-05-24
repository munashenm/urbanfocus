<div class="top-bar">
    <div class="container d-flex justify-content-between align-items-center py-2 small">
        <div>
            <a href="tel:0875501813" class="text-white text-decoration-none me-3">087 550 1813</a>
            <a href="mailto:sales@urbanfocus.co.za" class="text-white text-decoration-none">sales@urbanfocus.co.za</a>
        </div>
        <div>
            @auth
                @if(auth()->user()->isAdmin())
                    <a href="{{ route('admin.dashboard') }}" class="text-white text-decoration-none me-3">Admin</a>
                @endif
                <a href="{{ route('account.profile.edit') }}" class="text-white text-decoration-none me-3">My Profile</a>
                <a href="{{ route('account.dashboard') }}" class="text-white text-decoration-none me-3">My Account</a>
                <form action="{{ route('logout') }}" method="POST" class="d-inline">@csrf<button class="btn btn-link btn-sm text-white p-0">Logout</button></form>
            @else
                <a href="{{ route('login') }}" class="text-white text-decoration-none me-3">Login</a>
                <a href="{{ route('register') }}" class="text-white text-decoration-none">Register</a>
            @endauth
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom sticky-top">
    <div class="container">
        <a class="site-logo navbar-brand py-0" href="{{ route('home') }}">
            <img src="{{ asset('images/logo.png') }}" alt="Urban Focus — IT Products & Software" width="200" height="42">
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <form class="d-flex mx-lg-4 flex-grow-1 my-3 my-lg-0" action="{{ route('shop.index') }}" method="GET">
                <input class="form-control search-input" type="search" name="q" placeholder="Search IT products..." value="{{ request('q') }}">
                <button class="btn btn-primary ms-2" type="submit">Search</button>
            </form>
            <ul class="navbar-nav ms-auto align-items-lg-center">
                <li class="nav-item"><a class="nav-link" href="{{ route('shop.index') }}">Shop</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('contact') }}">Contact</a></li>
                <li class="nav-item ms-lg-3">
                    <a class="btn btn-outline-primary position-relative" href="{{ route('cart.index') }}">
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
