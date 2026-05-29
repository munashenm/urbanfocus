@extends('layouts.app')

@section('title', 'Order Confirmed | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-5 text-center">
    <div class="checkout-card mx-auto" style="max-width:600px">
        @if($order->payment_method === 'paystack' && $order->payment_status !== 'paid')
            <div class="text-warning mb-3" style="font-size:3rem">&#9203;</div>
            <h1 class="h2 fw-bold">Order Received</h1>
            <p class="text-muted">Your order <strong>{{ $order->order_number }}</strong> is awaiting payment confirmation from Paystack.</p>
            @if(session('success'))
                <div class="alert alert-info text-start mt-3 small">{{ session('success') }}</div>
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

        <p class="mt-3">A confirmation email has been sent to <strong>{{ $order->customer_email }}</strong>.</p>
        <div class="mt-4 d-flex flex-wrap justify-content-center gap-2">
            <a href="{{ route('shop.index') }}" class="btn btn-primary">Continue Shopping</a>
            @auth
                <a href="{{ route('orders.invoice', $order) }}" class="btn btn-outline-secondary" target="_blank">View Invoice</a>
                <a href="{{ route('account.orders.show', $order) }}" class="btn btn-outline-primary">View Order</a>
            @else
                <a href="{{ route('orders.track') }}" class="btn btn-outline-primary">Track This Order</a>
            @endauth
        </div>
    </div>
</div>
@endsection
