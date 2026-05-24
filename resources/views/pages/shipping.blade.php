@extends('layouts.app')

@section('title', 'Shipping & Returns | Urban Focus')

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">Shipping &amp; Returns</h1>
        <p class="mb-0 opacity-75">Delivery information and return policy.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="checkout-card mb-4">
                <h2 class="h5 fw-bold">Shipping</h2>
                <ul class="mb-0">
                    <li>Standard courier delivery across South Africa</li>
                    <li>Flat rate courier fee: <strong>R {{ number_format(config('shipping.flat_rate'), 2) }}</strong></li>
                    <li>Free shipping on orders over <strong>R {{ number_format(config('shipping.free_threshold'), 2) }}</strong></li>
                    <li>Manual courier quote available for large or bulk orders</li>
                    <li>Collection option available — contact us to arrange</li>
                </ul>
            </div>
            <div class="checkout-card mb-4">
                <h2 class="h5 fw-bold">Processing times</h2>
                <p class="mb-0">Orders are processed within 1–2 business days after payment confirmation. You will receive an email confirmation with your order details.</p>
            </div>
            <div class="checkout-card mb-4">
                <h2 class="h5 fw-bold">Returns &amp; warranty</h2>
                <p>Defective or incorrect items may be returned within 7 days of delivery. Products must be unused and in original packaging. Software licenses and opened consumables may not be returnable — contact us for guidance.</p>
                <p class="mb-0">Manufacturer warranties apply where applicable. For assistance, email <a href="mailto:sales@urbanfocus.co.za">sales@urbanfocus.co.za</a> or call <a href="tel:0875501813">087 550 1813</a>.</p>
            </div>
            <a href="{{ route('shop.index') }}" class="btn btn-primary">Continue Shopping</a>
        </div>
    </div>
</div>
@endsection
