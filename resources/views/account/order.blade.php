@extends('layouts.app')

@section('title', 'Order '.$order->order_number.' | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <h1 class="h2 fw-bold mb-4">Order {{ $order->order_number }}</h1>
    @include('partials.order-detail', ['order' => $order])
</div>
@endsection
