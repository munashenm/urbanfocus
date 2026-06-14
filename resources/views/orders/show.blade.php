@extends('layouts.app')

@section('title', 'Order '.$order->order_number.' | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h2 fw-bold mb-0">Order {{ $order->order_number }}</h1>
        <a href="{{ route('orders.track') }}" class="btn btn-outline-secondary btn-sm">Track Another Order</a>
    </div>

    @include('partials.order-detail', ['order' => $order])
</div>
@endsection
