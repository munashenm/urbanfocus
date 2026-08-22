@extends('layouts.app')

@section('title', 'Order Confirmed | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-5 text-center">
    <div class="checkout-card mx-auto" style="max-width:600px">
        @if($order->payment_method === 'paystack' && $order->payment_status !== 'paid')
            <div class="text-warning mb-3" style="font-size:3rem">&#9203;</div>
            <h1 class="h2 fw-bold">Finish your payment</h1>
            <p class="text-muted">Your order <strong>{{ $order->order_number }}</strong> is reserved. Complete payment to confirm it.</p>
            @if(session('error'))
                <div class="alert alert-warning text-start mt-3 small">{{ session('error') }}</div>
            @endif
            @if(session('success'))
                <div class="alert alert-info text-start mt-3 small">{{ session('success') }}</div>
            @endif
            @if($canRetryPayment ?? true)
                <a href="{{ route('checkout.paystack.pay', $order) }}" class="btn btn-primary btn-lg mt-3">Pay now with Paystack</a>
            @endif
        @else
            <div class="text-success mb-3" style="font-size:3rem">&#10003;</div>
            <h1 class="h2 fw-bold">Thank You!</h1>
            <p class="text-muted">Your order <strong>{{ $order->order_number }}</strong> has been received.</p>
        @endif

        @if($order->payment_method === 'eft')
            <div class="alert alert-info text-start mt-4">
                @include('partials.eft-instructions', ['order' => $order])
            </div>
        @endif

        <p class="mt-3">We’ll email <strong>{{ $order->customer_email }}</strong> as soon as the confirmation goes out. Need help? Call <a href="tel:0875501813">087 550 1813</a>.</p>
        <div class="mt-4 d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('shop.index') }}" class="btn {{ ($canRetryPayment ?? false) ? 'btn-outline-primary' : 'btn-primary' }}">Continue Shopping</a>
            @auth
                @if($order->isPaid())
                    <a href="{{ route('orders.invoice', $order) }}" class="btn btn-outline-secondary" target="_blank">View Invoice</a>
                @endif
                <a href="{{ route('account.orders.show', $order) }}" class="btn btn-outline-primary">View Order</a>
            @else
                <a href="{{ route('orders.track') }}" class="btn btn-outline-primary">Track This Order</a>
            @endauth
        </div>
    </div>
</div>
@endsection
