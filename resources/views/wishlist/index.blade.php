@extends('layouts.app')

@section('title', 'Wishlist | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="h2 fw-bold mb-0">Wishlist</h1>
        @if($products->count())
            <div class="d-flex flex-wrap gap-2">
                <form action="{{ route('wishlist.add-all-to-cart') }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-primary btn-sm">Move in-stock items to cart</button>
                </form>
                <form action="{{ route('wishlist.clear') }}" method="POST" onsubmit="return confirm('Clear your entire wishlist?')">
                    @csrf
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Clear wishlist</button>
                </form>
            </div>
        @endif
    </div>

    @if($products->count())
        <p class="text-muted mb-4">{{ $products->count() }} saved {{ $products->count() === 1 ? 'item' : 'items' }} — add them to cart when you are ready to order.</p>
        <div class="row g-4">
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3">
                    @include('partials.product-card', ['product' => $product])
                    <div class="d-grid gap-2 mt-2">
                        @if($product->isAvailable())
                            <form action="{{ route('wishlist.move-to-cart', $product) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm w-100">Move to cart</button>
                            </form>
                        @endif
                        <form action="{{ route('wishlist.remove', $product) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-outline-secondary btn-sm w-100">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="text-center py-5 checkout-card">
            <h2 class="h4 fw-bold mb-2">Your wishlist is empty</h2>
            <p class="text-muted mb-4">Tap the heart on any product to save it for later — useful when you are building a quote or waiting on budget approval.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-primary">Browse products</a>
        </div>
    @endif
</div>
@endsection
