@extends('layouts.app')

@section('title', 'Urban Focus - IT Products & Software | South Africa')

@section('content')
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <h1>IT Products &amp; Software for South Africa</h1>
            <p class="lead mb-4">Urban Focus supplies quality hardware, networking, components and software licensing with fast delivery and professional support.</p>
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('shop.index') }}" class="btn btn-primary btn-lg">Shop Now</a>
                <a href="{{ route('contact') }}" class="btn btn-outline-light btn-lg">Get a Quote</a>
            </div>
        </div>
    </div>
</section>

<section class="trust-bar py-2">
    <div class="container">
        <div class="row">
            <div class="col-6 col-md-3 trust-item"><strong>Fast Delivery</strong><span class="small text-muted">Nationwide courier</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Secure Payments</strong><span class="small text-muted">PayFast &amp; EFT</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Expert Support</strong><span class="small text-muted">087 550 1813</span></div>
            <div class="col-6 col-md-3 trust-item"><strong>Trusted Supplier</strong><span class="small text-muted">IT since day one</span></div>
        </div>
    </div>
</section>

@if($categories->count())
<section class="py-5">
    <div class="container">
        <h2 class="section-title">Shop by Category</h2>
        <div class="row g-3">
            @foreach($categories as $category)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('categories.show', $category) }}" class="category-pill">{{ $category->name }}</a>
                </div>
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
            <a href="{{ route('shop.index') }}" class="btn btn-outline-primary btn-sm">View All</a>
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

<section class="py-5 bg-light">
    <div class="container text-center">
        <h2 class="section-title">Need Help Choosing?</h2>
        <p class="text-muted mb-4">Our team can assist with product selection, bulk orders and manual courier quotes.</p>
        <a href="tel:0875501813" class="btn btn-primary me-2">Call 087 550 1813</a>
        <a href="mailto:sales@urbanfocus.co.za" class="btn btn-outline-primary">Email Us</a>
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
