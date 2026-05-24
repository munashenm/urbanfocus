@extends('layouts.app')

@section('title', 'About Urban Focus | IT Supplier South Africa')

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">About Urban Focus</h1>
        <p class="mb-0 opacity-75">Your trusted partner for IT products and software in South Africa.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-8">
            <h2 class="h4 fw-bold text-navy">Who we are</h2>
            <p>Urban Focus is a South African online supplier specialising in IT hardware, networking equipment, computer components, peripherals and software licensing. We serve businesses, resellers, schools and home users across the country.</p>
            <p>Our mission is simple: deliver quality products at competitive prices, with reliable support and fast nationwide delivery.</p>

            <h2 class="h4 fw-bold text-navy mt-4">Why choose us</h2>
            <div class="row g-3 mt-2">
                @foreach([
                    ['title' => 'Genuine Products', 'text' => 'Sourced from authorised distributors and trusted suppliers.'],
                    ['title' => 'Expert Advice', 'text' => 'Our team helps you choose the right products for your needs.'],
                    ['title' => 'Secure Checkout', 'text' => 'PayFast, card, instant EFT and manual EFT options available.'],
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
                <p class="mb-2"><strong>Phone:</strong> <a href="tel:0875501813">087 550 1813</a></p>
                <p class="mb-2"><strong>Email:</strong> <a href="mailto:sales@urbanfocus.co.za">sales@urbanfocus.co.za</a></p>
                <p class="mb-3"><strong>Web:</strong> www.urbanfocus.co.za</p>
                <a href="{{ route('contact') }}" class="btn btn-primary w-100">Send a Message</a>
            </div>
        </div>
    </div>
</div>
@endsection
