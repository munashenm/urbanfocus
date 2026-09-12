@extends('layouts.app')

@section('title', $pageSeo['title'] ?? 'About Urban Focus | IT Supplier South Africa')
@section('meta_description', $pageSeo['description'] ?? config('seo.defaults.description'))
@section('canonical', route('about'))
@section('og_title', $pageSeo['title'] ?? 'About Urban Focus')
@section('og_description', $pageSeo['description'] ?? config('seo.defaults.description'))

@push('schema')
<script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->aboutPageSchema(), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
<script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->breadcrumbSchema([
    ['name' => 'Home', 'url' => route('home')],
    ['name' => 'About Urban Focus', 'url' => route('about')],
]), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">About Urban Focus</h1>
        <p class="mb-0 opacity-75">South African IT hardware, electronics and technology supplier.</p>
    </div>
</div>

<div class="container py-5">
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item active">About</li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold text-navy">Who we are</h2>
            <p><strong>Urban Focus</strong> is a South African IT hardware, electronics and technology supplier. We sell networking equipment, business laptops, servers, storage, CCTV, fibre, VoIP systems, software licensing and specialist IT to organisations that need VAT invoices, courier delivery and procurement support.</p>
            <p>Our website is <a href="https://www.urbanfocus.co.za/">https://www.urbanfocus.co.za/</a>. We operate from Centurion, Gauteng and deliver nationwide across South Africa, including Johannesburg, Pretoria, Cape Town, Durban and regional centres.</p>
            <p>We focus on business and enterprise infrastructure rather than general consumer electronics. Typical customers include corporates, systems integrators, ISPs, schools, resellers and public-sector buyers.</p>

            <h2 class="h4 fw-bold text-navy mt-4">What Urban Focus sells</h2>
            <p>Urban Focus supplies IT products that South African businesses actually deploy: UniFi and MikroTik networking, switching and wireless, business computing, physical security electronics, and specialist items such as hardware security keys, industrial IoT and private-cloud software.</p>
            <ul>
                <li>Enterprise and SMB networking (wireless, switching, routing, fibre and PoE)</li>
                <li>Business laptops, desktops, servers and storage</li>
                <li>CCTV, access control and related security electronics</li>
                <li>Specialist technology including hardware security keys, industrial IoT and licensing</li>
            </ul>
            <p class="mb-3">Browse the <a href="{{ route('shop.index') }}">full catalogue</a>, shop by <a href="{{ route('brands.index') }}">brand</a>, or start from a <a href="{{ route('solutions.index') }}">solutions page</a> for a specific use case.</p>

            @if(($featuredCategories ?? collect())->isNotEmpty())
                <h3 class="h5 fw-bold mt-4">Major categories</h3>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @foreach($featuredCategories as $category)
                        <a href="{{ $category->url() }}" class="btn btn-outline-secondary btn-sm">{{ $category->name }}</a>
                    @endforeach
                </div>
            @endif

            @if(($featuredBrands ?? collect())->isNotEmpty())
                <h3 class="h5 fw-bold">Brands we supply</h3>
                <div class="d-flex flex-wrap gap-2 mb-4">
                    @foreach($featuredBrands as $brand)
                        <a href="{{ route('brands.show', $brand) }}" class="btn btn-outline-secondary btn-sm">{{ $brand->name }}</a>
                    @endforeach
                </div>
            @endif

            <h2 class="h4 fw-bold text-navy mt-4">Who we serve</h2>
            <p>Urban Focus is set up for buyers who need a local South African supplier: IT managers placing repeat fleet orders, installers building networks, schools and public-sector teams that require VAT invoices, and businesses that want courier delivery instead of grey-market imports.</p>
            <p>Corporate customers can <a href="{{ route('b2b.quote') }}">request a quote</a>, <a href="{{ route('b2b.rfq') }}">upload an RFQ</a>, or read about <a href="{{ route('b2b.procurement') }}">corporate procurement</a>.</p>

            <h2 class="h4 fw-bold text-navy mt-4">Why choose us</h2>
            <div class="row g-3 mt-2">
                @foreach([
                    ['title' => 'Genuine brand supply', 'text' => 'Genuine Ubiquiti, MikroTik, Dell, HP, Hikvision and leading IT brands sourced through trusted distribution channels.'],
                    ['title' => 'B2B & Bulk Procurement', 'text' => 'RFQ support, project quotes and repeat fleet orders for finance and IT teams.'],
                    ['title' => 'VAT Tax Invoices', 'text' => 'Compliant invoicing for registered businesses and procurement departments.'],
                    ['title' => 'Nationwide Delivery', 'text' => 'Courier delivery across South Africa with free shipping on qualifying orders.'],
                ] as $item)
                    <div class="col-md-6">
                        <div class="info-card h-100">
                            <h3 class="h6 fw-bold mb-2">{{ $item['title'] }}</h3>
                            <p class="small text-muted mb-0">{{ $item['text'] }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="col-lg-4">
            <div class="checkout-card">
                <h3 class="h5 fw-bold mb-3">Company details</h3>
                <p class="mb-2"><strong>Legal name:</strong> Urban Focus</p>
                <p class="mb-2"><strong>Website:</strong> <a href="https://www.urbanfocus.co.za/">www.urbanfocus.co.za</a></p>
                <p class="mb-2"><strong>Market:</strong> South Africa</p>
                @include('partials.business-address', ['block' => true, 'showLabel' => true, 'class' => 'mb-3'])
                <p class="mb-2"><strong>Phone:</strong> <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a></p>
                <p class="mb-2"><strong>Email:</strong> <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a></p>
                <p class="mb-3"><strong>Hours:</strong> {{ config('business.hours') }}</p>
                <a href="{{ route('b2b.quote') }}" class="btn btn-outline-primary w-100 mb-2">Request a Quote</a>
                <a href="{{ route('contact') }}" class="btn btn-primary w-100">Contact Urban Focus</a>
            </div>
        </div>
    </div>
</div>
@endsection
