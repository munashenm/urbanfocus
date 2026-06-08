@extends('layouts.app')

@section('title', 'Urban Focus — IT Supplier South Africa | Networking, Laptops & Security')
@section('meta_description', 'Buy laptops, networking, CCTV, servers and IT equipment in South Africa. Urban Focus supplies Ubiquiti, Hikvision, Dell, TP-Link and more with nationwide delivery and VAT invoices.')
@section('meta_keywords', 'buy laptops South Africa, networking equipment South Africa, Ubiquiti supplier South Africa, Hikvision supplier South Africa, business IT supplier, computer accessories South Africa')
@section('og_image', asset('images/logo-stacked.png'))

@section('content')
{{-- Hero carousel --}}
<section class="hero-carousel">
    <div id="heroCarousel" class="carousel slide carousel-fade" data-bs-ride="carousel">
        <div class="carousel-indicators">
            @foreach($heroSlides as $i => $slide)
                <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="{{ $i }}" @if($i===0) class="active" @endif aria-label="Slide {{ $i+1 }}"></button>
            @endforeach
        </div>
        <div class="carousel-inner">
            @foreach($heroSlides as $i => $slide)
                <div class="carousel-item hero-slide hero-slide--{{ $slide['theme'] ?? 'navy' }} @if($i===0) active @endif">
                    <div class="container">
                        <div class="row align-items-center min-vh-50">
                            <div class="col-lg-7 hero-content py-5">
                                <span class="hero-eyebrow">{{ $slide['eyebrow'] }}</span>
                                <h1 class="hero-title">{{ $slide['title'] }}</h1>
                                <p class="hero-lead">{{ $slide['subtitle'] }}</p>
                                <div class="d-flex flex-wrap gap-2 mb-4">
                                    @php
                                        $primary = $slide['cta_primary'];
                                        $primaryUrl = isset($primary['params'])
                                            ? route($primary['route'], $primary['params'])
                                            : route($primary['route']);
                                    @endphp
                                    <a href="{{ $primaryUrl }}" class="btn btn-primary btn-lg">{{ $primary['label'] }}</a>
                                    @if(!empty($slide['cta_secondary']))
                                        @php $sec = $slide['cta_secondary']; @endphp
                                        <a href="{{ route($sec['route']) }}" class="btn btn-outline-light btn-lg">{{ $sec['label'] }}</a>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev"><span class="carousel-control-prev-icon"></span></button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next"><span class="carousel-control-next-icon"></span></button>
    </div>
</section>

@if($banners->count())
<section class="promo-banners py-4">
    <div class="container">
        <div class="row g-3">
            @foreach($banners as $banner)
                <div class="col-md-{{ $banners->count() === 1 ? '12' : ($banners->count() === 2 ? '6' : '3') }}">
                    <a href="{{ $banner->link ?: route('shop.index') }}" class="promo-banner-card d-block h-100">
                        @if($banner->image)
                            <img src="{{ storage_public_url($banner->image) }}" alt="{{ $banner->title }}" class="promo-banner-img" loading="lazy">
                        @endif
                        <div class="promo-banner-body">
                            <strong>{{ $banner->title }}</strong>
                            @if($banner->subtitle)<span class="d-block small opacity-75">{{ $banner->subtitle }}</span>@endif
                            @if($banner->button_text)<span class="promo-card-link">{{ $banner->button_text }} →</span>@endif
                        </div>
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
            <div class="col-6 col-md-3 trust-item"><strong>Fast Delivery</strong><span class="small text-muted">Nationwide</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Secure Checkout</strong><span class="small text-muted">Paystack &amp; EFT</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Authorised Supply</strong><span class="small text-muted">Genuine products</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Warranty Support</strong><span class="small text-muted">After-sales care</span></div>
        </div>
    </div>
</section>

@if($brands->count())
<section class="py-5 brand-showcase">
    <div class="container">
        @include('partials.section-header', [
            'title' => 'Featured Brands',
            'subtitle' => 'Authorised supply of leading networking, computing and security brands.',
            'url' => route('shop.index'),
            'linkLabel' => 'Shop All Brands',
        ])
        @include('partials.brand-logos', ['brands' => $brands])
    </div>
</section>
@endif

@if($categories->count())
<section class="py-5 bg-light">
    <div class="container">
        @include('partials.section-header', [
            'title' => 'Shop by Category',
            'subtitle' => 'Professional IT categories for business, installers and resellers.',
            'url' => route('shop.index'),
        ])
        <div class="row g-3">
            @foreach($categories as $category)
                <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                    <a href="{{ route('categories.show', $category) }}" class="category-card category-card--premium">
                        <span class="category-card-icon">{{ $categoryIcons[$category->slug] ?? '📦' }}</span>
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

{{-- Solution blocks --}}
<section class="py-5 solution-blocks">
    <div class="container">
        <div class="row g-4">
            @foreach($solutionBlocks as $block)
                @php $cat = $categories->firstWhere('slug', $block['category_slug']); @endphp
                <div class="col-md-6 col-lg-3">
                    <a href="{{ $cat ? route('categories.show', $cat) : route('shop.index', ['category' => $block['category_slug']]) }}" class="solution-block-card">
                        <span class="solution-block-icon">{{ $categoryIcons[$block['category_slug']] ?? '⚡' }}</span>
                        <h3 class="h5 fw-bold mb-1">{{ $block['title'] }}</h3>
                        <p class="small text-muted mb-2">{{ $block['subtitle'] }}</p>
                        <span class="solution-block-link">Browse →</span>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
</section>

@if($dealProducts->count())
<section class="py-5">
    <div class="container">
        @include('partials.section-header', [
            'title' => 'Daily Deals',
            'subtitle' => 'Limited-time specials on selected IT products.',
            'url' => route('shop.index', ['deals' => 1]),
            'linkLabel' => 'All Deals',
        ])
        <div class="row g-4">
            @foreach($dealProducts as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
    </div>
</section>
@endif

@if($topSellers->count())
    @include('partials.home-product-section', [
        'title' => 'Top Sellers',
        'subtitle' => 'Networking, security and IT products from leading brands like Dahua, TP-Link, Ubiquiti, Dell and Hikvision.',
        'url' => route('shop.index', ['category' => 'networking', 'sort' => 'popular']),
        'linkLabel' => 'View All',
        'products' => $topSellers,
        'sectionKey' => 'top_sellers',
        'sectionBrands' => $sectionBrands,
        'bgLight' => true,
    ])
@endif

@if($laptopProducts->count())
    @include('partials.home-product-section', [
        'title' => 'Business Laptops',
        'subtitle' => 'Corporate notebooks and mobile workstations from Dell, HP, Lenovo and more.',
        'url' => route('shop.index', ['category' => 'laptops-notebooks']),
        'products' => $laptopProducts,
        'sectionKey' => 'laptops-notebooks',
        'sectionBrands' => $sectionBrands,
    ])
@endif

@if($networkingProducts->count())
    @include('partials.home-product-section', [
        'title' => 'Networking Solutions',
        'subtitle' => 'Switches, access points, routers and fibre for ISPs and businesses.',
        'url' => route('shop.index', ['category' => 'networking']),
        'products' => $networkingProducts,
        'bgLight' => true,
    ])
@endif

@if($featuredProducts->count())
<section class="py-5">
    <div class="container">
        @include('partials.section-header', ['title' => 'Featured Products', 'url' => route('shop.index')])
        <div class="row g-4">
            @foreach($featuredProducts as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
    </div>
</section>
@endif

@include('partials.testimonials')

@if(($featuredArticle ?? null) || $articles->count())
<section class="py-5">
    <div class="container">
        @include('partials.section-header', [
            'title' => 'IT Insights & Guides',
            'subtitle' => 'Buying guides, product news and industry updates.',
            'url' => route('blog.index'),
            'linkLabel' => 'View Blog',
        ])
        @if($featuredArticle ?? null)
            <div class="mb-4">
                @include('partials.article-card', ['article' => $featuredArticle, 'featured' => true])
            </div>
        @endif
        @if($articles->count())
            <div class="row g-4">
                @foreach($articles as $article)
                    <div class="col-md-4">
                        @include('partials.article-card', ['article' => $article])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
@endif

<section class="py-5 b2b-cta">
    <div class="container">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <h2 class="h3 fw-bold text-white mb-2">Corporate, Government &amp; Bulk Orders</h2>
                <p class="text-white-50 mb-0">Dedicated account support, formal quotes, RFQ processing, bulk pricing and procurement assistance.</p>
            </div>
            <div class="col-lg-4 d-flex flex-wrap gap-2 justify-content-lg-end">
                <a href="{{ route('b2b.quote') }}" class="btn btn-light">Request Quote</a>
                <a href="{{ route('b2b.rfq') }}" class="btn btn-outline-light">Upload RFQ</a>
                <a href="{{ route('b2b.procurement') }}" class="btn btn-outline-light">Procurement</a>
            </div>
        </div>
    </div>
</section>
@endsection

@push('schema')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "WebSite",
    "name": "Urban Focus",
    "url": "{{ config('app.url') }}",
    "potentialAction": {
        "@type": "SearchAction",
        "target": "{{ route('shop.index') }}?q={search_term_string}",
        "query-input": "required name=search_term_string"
    }
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Urban Focus",
    "url": "{{ config('app.url') }}",
    "logo": "{{ asset('images/logo-stacked.png') }}",
    "email": "sales@urbanfocus.co.za",
    "telephone": "+27875501813",
    "address": { "@type": "PostalAddress", "streetAddress": "{{ config('business.address.line1') }}", "addressLocality": "{{ config('business.address.city') }}", "addressRegion": "{{ config('business.address.province') }}", "addressCountry": "ZA" },
    "sameAs": [
        @if(config('social.facebook'))"{{ config('social.facebook') }}",@endif
        @if(config('social.instagram'))"{{ config('social.instagram') }}",@endif
        @if(config('social.x'))"{{ config('social.x') }}",@endif
        @if(config('social.tiktok'))"{{ config('social.tiktok') }}"@endif
    ]
}
</script>
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "Urban Focus",
    "url": "{{ config('app.url') }}",
    "logo": "{{ asset('images/logo-stacked.png') }}",
    "image": "{{ asset('images/logo-stacked.png') }}",
    "email": "sales@urbanfocus.co.za",
    "telephone": "+27875501813",
    "address": {
        "@type": "PostalAddress",
        "streetAddress": "{{ config('business.address.line1') }}",
        "addressLocality": "{{ config('business.address.city') }}",
        "addressRegion": "{{ config('business.address.province') }}",
        "addressCountry": "ZA"
    },
    "areaServed": "ZA",
    "priceRange": "$$"
}
</script>
@php $faqSchema = app(\App\Services\SeoService::class)->faqSchema(); @endphp
@if($faqSchema !== [])
<script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@endif
@endpush
