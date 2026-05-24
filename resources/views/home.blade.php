@extends('layouts.app')

@section('title', 'Urban Focus — South Africa\'s Trusted IT Supplier')

@section('content')
<section class="hero hero--premium">
    <div class="container">
        <div class="hero-content">
            <span class="hero-eyebrow">Enterprise &amp; SME IT Supply</span>
            <h1>South Africa's Trusted Supplier of IT Products &amp; Software</h1>
            <p class="lead mb-4">Hardware, networking, licensing and components — backed by professional support, VAT invoices, and nationwide delivery.</p>
            <div class="d-flex flex-wrap gap-2 mb-4">
                <a href="{{ route('shop.index') }}" class="btn btn-primary btn-lg">Shop Now</a>
                <a href="{{ route('b2b.quote') }}" class="btn btn-outline-light btn-lg">Request a Quote</a>
                <a href="{{ route('b2b.rfq') }}" class="btn btn-outline-light btn-lg">Upload RFQ</a>
            </div>
            <div class="hero-trust row g-2 g-md-3">
                @foreach(['Secure Payments', 'Nationwide Delivery', 'VAT Invoices', 'Business Quotes', 'Warranty Support'] as $badge)
                    <div class="col-6 col-md-auto"><span class="hero-trust-badge">{{ $badge }}</span></div>
                @endforeach
            </div>
        </div>
    </div>
</section>

@if($banners->count())
<section class="promo-strip py-3">
    <div class="container">
        <div class="row g-3">
            @foreach($banners as $banner)
                <div class="col-md-{{ $banners->count() === 1 ? '12' : ($banners->count() === 2 ? '6' : '4') }}">
                    <a href="{{ $banner->link ?: route('shop.index') }}" class="promo-card d-block">
                        <strong>{{ $banner->title }}</strong>
                        @if($banner->subtitle)<span class="d-block small opacity-75">{{ $banner->subtitle }}</span>@endif
                        @if($banner->button_text)<span class="promo-card-link">{{ $banner->button_text }} →</span>@endif
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="trust-bar py-3">
    <div class="container">
        <div class="row g-0">
            <div class="col-6 col-md-3 trust-item"><strong>Fast Delivery</strong><span class="small text-muted">Courier nationwide</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Secure Checkout</strong><span class="small text-muted">PayFast &amp; EFT</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>B2B Support</strong><span class="small text-muted">087 550 1813</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Trusted Since Day One</strong><span class="small text-muted">Genuine products</span></div>
        </div>
    </div>
</section>

@if($categories->count())
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0">Shop by Category</h2>
            <a href="{{ route('shop.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
        </div>
        <div class="row g-3">
            @foreach($categories as $category)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('categories.show', $category) }}" class="category-card">
                        <span class="category-card-title">{{ $category->name }}</span>
                        @if($category->children->count())
                            <span class="category-card-sub">{{ $category->children->count() }} subcategories</span>
                        @endif
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($brands->count())
<section class="py-4 bg-light brand-carousel-section">
    <div class="container">
        <h2 class="section-title text-center mb-4">Leading Brands We Supply</h2>
        <div class="brand-carousel d-flex flex-wrap justify-content-center gap-3">
            @foreach($brands as $brand)
                <a href="{{ route('shop.index', ['brand' => $brand->name]) }}" class="brand-pill">
                    @if(!empty($brand->logo))
                        <img src="{{ asset('storage/'.$brand->logo) }}" alt="{{ $brand->name }}" height="28" loading="lazy">
                    @else
                        {{ $brand->name }}
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($dealProducts->count())
<section class="py-5">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0">Deals &amp; Specials</h2>
            <a href="{{ route('shop.index', ['sort' => 'price_asc']) }}" class="btn btn-outline-primary btn-sm">View All Deals</a>
        </div>
        <div class="row g-4">
            @foreach($dealProducts as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($featuredProducts->count())
<section class="py-5 bg-light">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="section-title mb-0">Featured Products</h2>
            <a href="{{ route('shop.index') }}" class="btn btn-outline-primary btn-sm">Browse Shop</a>
        </div>
        <div class="row g-4">
            @foreach($featuredProducts as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($newProducts->count())
<section class="py-5">
    <div class="container">
        <h2 class="section-title">New Arrivals</h2>
        <div class="row g-4">
            @foreach($newProducts as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
    </div>
</section>
@endif

<section class="py-5 b2b-cta">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <h2 class="h3 fw-bold text-white mb-2">Corporate, Government &amp; Bulk Orders</h2>
                <p class="text-white-50 mb-0">Dedicated account support, formal quotes, RFQ processing, and procurement assistance for businesses across South Africa.</p>
            </div>
            <div class="col-lg-4 d-flex flex-wrap gap-2 justify-content-lg-end">
                <a href="{{ route('b2b.procurement') }}" class="btn btn-light">Procurement</a>
                <a href="{{ route('b2b.source') }}" class="btn btn-outline-light">Source a Product</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('schema')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Urban Focus",
    "url": "{{ config('app.url') }}",
    "email": "sales@urbanfocus.co.za",
    "telephone": "+27875501813",
    "address": { "@type": "PostalAddress", "addressCountry": "ZA" }
}
</script>
@endpush
