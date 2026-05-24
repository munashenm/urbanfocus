<footer class="site-footer mt-5">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="footer-logo-badge">
                    <a href="{{ route('home') }}" class="site-logo site-logo--footer">
                        <img src="{{ asset('images/logo-stacked.png') }}" alt="Urban Focus" width="160" height="72">
                    </a>
                </div>
                <p class="text-white-50">South African online supplier of IT products and software. Quality hardware, licensing, and professional support.</p>
            </div>
            <div class="col-md-4 col-lg-2">
                <h6 class="text-white mb-3">Shop</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="{{ route('shop.index') }}">All Products</a></li>
                    <li><a href="{{ route('shop.index', ['sort' => 'newest']) }}">New Arrivals</a></li>
                </ul>
            </div>
            <div class="col-md-4 col-lg-3">
                <h6 class="text-white mb-3">Contact</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="tel:0875501813">087 550 1813</a></li>
                    <li><a href="mailto:sales@urbanfocus.co.za">sales@urbanfocus.co.za</a></li>
                    <li><a href="{{ route('contact') }}">Contact Form</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <h6 class="text-white mb-3">Secure Payments</h6>
                <p class="text-white-50 small mb-0">PayFast, EFT &amp; trusted courier delivery across South Africa.</p>
            </div>
        </div>
    </div>
    <div class="footer-bottom py-3">
        <div class="container d-flex flex-wrap justify-content-between small text-white-50">
            <span>&copy; {{ date('Y') }} Urban Focus. All rights reserved.</span>
            <span>www.urbanfocus.co.za</span>
        </div>
    </div>
</footer>
