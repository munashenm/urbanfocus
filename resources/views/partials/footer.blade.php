<footer class="site-footer mt-auto">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <a href="{{ route('home') }}" class="site-logo site-logo--footer d-inline-block mb-3">
                    <img src="{{ asset('images/logo-footer.png') }}" alt="Urban Focus" width="253" height="24" class="footer-logo" loading="lazy">
                </a>
                <p class="text-white-50 mb-3">South African supplier of IT hardware, networking, components and software licensing. Professional support and nationwide delivery.</p>
                @include('partials.social-links', ['title' => 'Follow us', 'class' => 'mt-2'])
                <div class="mt-4">
                    <h6 class="text-white mb-2">Deals &amp; Updates</h6>
                    <form action="{{ route('newsletter.store') }}" method="POST" class="newsletter-form d-flex gap-2">
                        @csrf
                        <label for="newsletter-email" class="visually-hidden">Email address</label>
                        <input id="newsletter-email" type="email" name="email" class="form-control form-control-sm" placeholder="Your email" required>
                        <button type="submit" class="btn btn-primary btn-sm text-nowrap">Subscribe</button>
                    </form>
                </div>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-white mb-3">Quick Links</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="{{ route('home') }}">Home</a></li>
                    <li><a href="{{ route('shop.index') }}">Products</a></li>
                    <li><a href="{{ route('brands.index') }}">Brands</a></li>
                    <li><a href="{{ route('about') }}">About Us</a></li>
                    <li><a href="{{ route('contact') }}">Contact Us</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-white mb-3">Information</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="{{ route('privacy') }}">Privacy Policy</a></li>
                    <li><a href="{{ route('terms') }}">Terms &amp; Conditions</a></li>
                    <li><a href="{{ route('warranty') }}">Warranty Terms</a></li>
                    <li><a href="{{ route('popia') }}">POPIA</a></li>
                    <li><a href="{{ route('careers') }}">Work With Us</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-white mb-3">Customer Service</h6>
                <ul class="list-unstyled footer-links">
                    <li><a href="{{ route('contact') }}">Contact Us</a></li>
                    <li><a href="{{ route('returns') }}">Returns</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <h6 class="text-white mb-3">Get in Touch</h6>
                <ul class="list-unstyled footer-links mb-2">
                    <li><a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a></li>
                    <li><a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a></li>
                    @include('partials.business-address', ['showLabel' => false, 'class' => 'mt-2'])
                </ul>
                <p class="footer-text small mb-0">{{ config('business.hours') }}</p>
                @if(config('business.vat_number') || config('business.company_reg'))
                    <p class="footer-text small mt-2 mb-0">
                        @if(config('business.company_reg'))Reg: {{ config('business.company_reg') }}@endif
                        @if(config('business.vat_number')) · VAT: {{ config('business.vat_number') }}@endif
                    </p>
                @endif
            </div>
        </div>
    </div>
    <div class="footer-bottom py-3">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <span class="small footer-text">&copy; {{ date('Y') }} Urban Focus. All rights reserved.</span>
                <img src="{{ asset('images/partners/visa-mastercard.png') }}" alt="Visa and Mastercard accepted" class="footer-payment-logos" width="120" height="32" loading="lazy">
                <div class="small footer-bottom-links">
                    <a href="{{ route('privacy') }}" class="text-decoration-none me-3">Privacy</a>
                    <a href="{{ route('terms') }}" class="text-decoration-none me-3">Terms</a>
                    <a href="{{ route('popia') }}" class="text-decoration-none">POPIA</a>
                </div>
            </div>
        </div>
    </div>
</footer>
