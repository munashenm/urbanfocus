<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <a href="{{ route('home') }}" class="site-logo site-logo--footer d-inline-block mb-3">
                    <img src="{{ asset('images/logo-stacked.png') }}" alt="Urban Focus" width="160" height="72">
                </a>
                <p class="text-white-50 mb-3">South African supplier of IT hardware, networking, components and software licensing. Professional support and nationwide delivery.</p>
                @include('partials.partner-logos', ['title' => 'Secure payments & delivery', 'class' => 'partner-logos--footer'])
                @include('partials.social-links', ['title' => 'Follow us', 'class' => 'mt-3'])
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h6 class="text-white mb-3">Shop</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="{{ route('shop.index') }}">All Products</a></li>
                    @foreach(($navCategories ?? collect())->take(5) as $cat)
                        <li><a href="{{ route('categories.show', $cat) }}">{{ $cat->name }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="col-6 col-md-4 col-lg-2">
                <h6 class="text-white mb-3">Company</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="{{ route('about') }}">About Us</a></li>
                    <li><a href="{{ route('contact') }}">Contact</a></li>
                    <li><a href="{{ route('b2b.quote') }}">Request a Quote</a></li>
                    <li><a href="{{ route('b2b.rfq') }}">Upload RFQ</a></li>
                    <li><a href="{{ route('b2b.procurement') }}">Procurement</a></li>
                    <li><a href="{{ route('shipping') }}">Shipping &amp; Returns</a></li>
                </ul>
            </div>
            <div class="col-md-4 col-lg-4">
                <h6 class="text-white mb-3">Get in Touch</h6>
                <ul class="list-unstyled footer-links mb-3">
                    <li><a href="tel:0875501813">087 550 1813</a></li>
                    <li><a href="mailto:sales@urbanfocus.co.za">sales@urbanfocus.co.za</a></li>
                    <li><a href="https://www.urbanfocus.co.za">www.urbanfocus.co.za</a></li>
                </ul>
                <p class="text-white-50 small mb-0">Mon–Fri 8:00–17:00 · Bulk &amp; quote orders welcome</p>
            </div>
        </div>
    </div>
    <div class="footer-bottom py-3">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-2 small text-white-50">
            <span>&copy; {{ date('Y') }} Urban Focus. All rights reserved.</span>
            <div>
                <a href="{{ route('privacy') }}" class="text-white-50 text-decoration-none me-3">Privacy</a>
                <a href="{{ route('terms') }}" class="text-white-50 text-decoration-none">Terms</a>
            </div>
        </div>
    </div>
</footer>
