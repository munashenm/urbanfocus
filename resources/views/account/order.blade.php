@extends('layouts.app')

@section('title', 'Order '.$order->order_number.' | Urban Focus')

@section('content')
<div class="container py-4">
    <h1 class="h2 fw-bold mb-4">Order {{ $order->order_number }}</h1>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="checkout-card">
                <h2 class="h5 fw-bold mb-3">Items</h2>
                <table class="table">
                    <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                    <tbody>
                        @foreach($order->items as $item)
                            <tr>
                                <td>{{ $item->product_name }}</td>
                                <td>{{ $item->quantity }}</td>
                                <td>R {{ number_format($item->unit_price, 2) }}</td>
                                <td>R {{ number_format($item->line_total, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="checkout-card">
                <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
                <p><strong>Payment:</strong> {{ ucfirst($order->payment_status) }} ({{ strtoupper($order->payment_method) }})</p>
                <hr>
                <p>Subtotal: R {{ number_format($order->subtotal, 2) }}</p>
                <p>Shipping: R {{ number_format($order->shipping_cost, 2) }}</p>
                <p>VAT: R {{ number_format($order->tax_amount, 2) }}</p>
                <p class="h5">Total: R {{ number_format($order->total, 2) }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
