@extends('layouts.app')

@section('title', 'Order Confirmed | Urban Focus')

@section('content')
<div class="container py-5 text-center">
    <div class="checkout-card mx-auto" style="max-width:600px">
        <div class="text-success mb-3" style="font-size:3rem">&#10003;</div>
        <h1 class="h2 fw-bold">Thank You!</h1>
        <p class="text-muted">Your order <strong>{{ $order->order_number }}</strong> has been received.</p>

        @if($order->payment_method === 'eft')
            <div class="alert alert-info text-start mt-4">
                <strong>EFT Payment Instructions</strong>
                <p class="mb-1 mt-2">Please use order number <strong>{{ $order->order_number }}</strong> as your payment reference.</p>
                <p class="mb-0">Amount due: <strong>R {{ number_format($order->total, 2) }}</strong></p>
                <p class="small text-muted mt-2 mb-0">Bank details will be emailed to you. Contact sales@urbanfocus.co.za for assistance.</p>
            </div>
        @endif

        <p class="mt-3">A confirmation email has been sent to <strong>{{ $order->customer_email }}</strong>.</p>
        <div class="mt-4">
            <a href="{{ route('shop.index') }}" class="btn btn-primary">Continue Shopping</a>
            @auth
                <a href="{{ route('account.orders.show', $order) }}" class="btn btn-outline-primary">View Order</a>
            @endauth
        </div>
    </div>
</div>
@endsection
