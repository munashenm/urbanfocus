@extends('layouts.app')

@section('title', 'Shopping Cart | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <h1 class="h2 fw-bold mb-4">Shopping Cart</h1>

    @if($items->count())
        <div class="cart-table-wrap">
            <form action="{{ route('cart.update') }}" method="POST">
                @csrf
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th width="120">Qty</th>
                                <th>Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($items as $item)
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <a href="{{ route('products.show', $item['product']) }}" class="cart-item-thumb">
                                                <img src="{{ $item['product']->display_image_url }}" alt="{{ $item['product']->imageAlt() }}" width="64" height="64">
                                            </a>
                                            <div>
                                                <a href="{{ route('products.show', $item['product']) }}" class="fw-semibold text-decoration-none">{{ $item['product']->name }}</a>
                                                @if($item['product']->sku)<br><small class="text-muted">{{ $item['product']->sku }}</small>@endif
                                                <div class="mt-1">
                                                    <button
                                                        type="submit"
                                                        class="btn btn-link btn-sm p-0"
                                                        formaction="{{ route('cart.save-for-later', $item['product']) }}"
                                                        formmethod="POST"
                                                    >Save for later</button>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>R {{ number_format($item['product']->effective_price, 2) }}</td>
                                    <td><input type="number" name="quantities[{{ $item['product']->id }}]" value="{{ $item['quantity'] }}" min="0" class="form-control form-control-sm" aria-label="Quantity for {{ $item['product']->name }}"></td>
                                    <td class="fw-semibold">R {{ number_format($item['line_total'], 2) }}</td>
                                    <td>
                                        <button
                                            type="submit"
                                            class="btn btn-sm btn-outline-danger"
                                            formaction="{{ route('cart.remove', $item['product']) }}"
                                            formmethod="POST"
                                            aria-label="Remove {{ $item['product']->name }}"
                                        >&times;</button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <button type="submit" class="btn btn-outline-primary btn-sm">Update Cart</button>
                    <div class="text-end">
                        <div class="h5 mb-0">Subtotal: <strong>R {{ number_format($subtotal, 2) }}</strong></div>
                        <small class="text-muted">Shipping calculated at checkout</small>
                    </div>
                </div>
            </form>
        </div>
        <div class="text-end mt-4">
            <a href="{{ route('shop.index') }}" class="btn btn-outline-secondary me-2">Continue Shopping</a>
            <a href="{{ route('checkout.index') }}" class="btn btn-primary btn-lg">Proceed to Checkout</a>
        </div>
    @else
        <div class="text-center py-5 checkout-card">
            <h2 class="h4 fw-bold mb-2">Your cart is empty</h2>
            <p class="text-muted mb-4">Browse networking, laptops and security products — or check your wishlist for items you saved earlier.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-primary me-2">Start Shopping</a>
            <a href="{{ route('wishlist.index') }}" class="btn btn-outline-primary">View wishlist</a>
        </div>
    @endif
</div>
@endsection
