@extends('layouts.app')

@section('title', 'Session Expired | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-5 text-center">
    <div class="checkout-card mx-auto" style="max-width:520px">
        <h1 class="h4 fw-bold mb-3">Your checkout session expired</h1>
        <p class="text-muted mb-4">This can happen if the page was left open too long. Your cart is usually still saved — open checkout and try again.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="{{ route('checkout.index') }}" class="btn btn-primary">Return to checkout</a>
            <a href="{{ route('cart.index') }}" class="btn btn-outline-primary">View cart</a>
            <a href="tel:0875501813" class="btn btn-outline-secondary">Call 087 550 1813</a>
        </div>
    </div>
</div>
@endsection
