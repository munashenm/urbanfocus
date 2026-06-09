@extends('layouts.app')

@section('title', $pageSeo['title'] ?? 'About Urban Focus | IT Supplier South Africa')
@section('meta_description', $pageSeo['description'] ?? config('seo.defaults.description'))

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">About Urban Focus</h1>
        <p class="mb-0 opacity-75">Professional South African IT distributor for business procurement, networking and enterprise hardware.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold text-navy">Who we are</h2>
            <p>Urban Focus is a South African IT distributor supplying networking equipment, business laptops, servers, CCTV, fibre solutions, VoIP systems and software licensing to corporates, integrators, ISPs, schools and resellers nationwide.</p>
            <p>We focus on business and enterprise infrastructure — not general consumer electronics. Every order includes VAT-compliant tax invoicing, professional support and courier delivery across South Africa.</p>

            <h2 class="h4 fw-bold text-navy mt-4">Why choose us</h2>
            <div class="row g-3 mt-2">
                @foreach([
                    ['title' => 'Authorised Brand Supply', 'text' => 'Genuine Ubiquiti, MikroTik, Dell, HP, Hikvision and leading IT brands from trusted distribution channels.'],
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
                <h3 class="h5 fw-bold mb-3">Contact us</h3>
                @include('partials.business-address', ['block' => true, 'showLabel' => false, 'class' => 'mb-3'])
                <p class="mb-2"><strong>Phone:</strong> <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a></p>
                <p class="mb-2"><strong>Email:</strong> <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a></p>
                <p class="mb-3"><strong>Hours:</strong> {{ config('business.hours') }}</p>
                <a href="{{ route('b2b.quote') }}" class="btn btn-outline-primary w-100 mb-2">Request a Quote</a>
                <a href="{{ route('contact') }}" class="btn btn-primary w-100">Send a Message</a>
            </div>
        </div>
    </div>
</div>
@endsection
