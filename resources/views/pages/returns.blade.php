@extends('layouts.app')

@section('title', $pageSeo['title'] ?? 'Returns Policy | Urban Focus')
@section('meta_description', $pageSeo['description'] ?? config('seo.defaults.description'))

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">Returns</h1>
        <p class="mb-0 opacity-75">How to return or exchange products purchased from Urban Focus.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8 legal-content">
            <div class="checkout-card mb-4">
                <h2 class="h5 fw-bold">Return eligibility</h2>
                <p>Defective, damaged or incorrectly supplied items may be returned within <strong>7 calendar days</strong> of delivery, subject to the conditions below.</p>
                <ul>
                    <li>Product must be unused and in original packaging with all accessories</li>
                    <li>Proof of purchase (order number or invoice) is required</li>
                    <li>Software licences, digital products and opened consumables are generally not returnable</li>
                    <li>Custom-built or special-order items may not be eligible for return</li>
                </ul>
            </div>
            <div class="checkout-card mb-4">
                <h2 class="h5 fw-bold">How to start a return</h2>
                <ol>
                    <li>Email <a href="mailto:{{ config('business.email') }}">{{ config('business.email') }}</a> or call <a href="tel:{{ config('business.phone_tel') }}">{{ config('business.phone') }}</a> with your order number and reason for return</li>
                    <li>Our team will confirm eligibility and provide return instructions</li>
                    <li>Once received and inspected, approved refunds are processed to the original payment method</li>
                </ol>
            </div>
            <div class="checkout-card mb-4">
                <h2 class="h5 fw-bold">Refunds &amp; exchanges</h2>
                <p>Refunds exclude original shipping costs unless the return is due to our error. Exchanges are subject to stock availability. Courier collection or return shipping costs may apply unless the product was faulty or incorrectly supplied.</p>
            </div>
            <p class="mb-0">For delivery information see our <a href="{{ route('shipping') }}">Shipping &amp; Returns</a> page. For warranty claims see <a href="{{ route('warranty') }}">Warranty Terms</a>.</p>
        </div>
    </div>
</div>
@endsection
