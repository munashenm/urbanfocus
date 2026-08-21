@extends('layouts.app')

@section('title', 'Compare Products | Urban Focus')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<div class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h1 class="h2 fw-bold mb-1">Compare products</h1>
            <p class="text-muted mb-0">Side-by-side specs for up to {{ \App\Services\CompareService::MAX_ITEMS }} products. {{ $remaining }} {{ $remaining === 1 ? 'slot' : 'slots' }} remaining.</p>
        </div>
        @if($products->count())
            <form action="{{ route('compare.clear') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-sm">Clear comparison</button>
            </form>
        @endif
    </div>

    @if($products->count() >= 2)
        <div class="compare-table-wrap">
            <table class="table compare-table align-middle mb-0">
                <thead>
                    <tr>
                        <th class="compare-label">Product</th>
                        @foreach($products as $product)
                            <th class="compare-product">
                                <a href="{{ route('products.show', $product) }}" class="d-block mb-2">
                                    <img src="{{ $product->display_image_url }}" alt="{{ $product->imageAlt() }}" width="140" height="140" class="compare-image">
                                </a>
                                <a href="{{ route('products.show', $product) }}" class="fw-semibold text-decoration-none d-block mb-2">{{ $product->name }}</a>
                                <form action="{{ route('compare.remove', $product) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-link btn-sm p-0 text-muted">Remove</button>
                                </form>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <th class="compare-label">Price</th>
                        @foreach($products as $product)
                            <td>
                                @if($product->is_on_sale)
                                    <span class="price-old d-block">R {{ number_format($product->price, 2) }}</span>
                                @endif
                                <strong>R {{ number_format($product->effective_price, 2) }}</strong>
                                @if($product->discountPercent())
                                    <span class="badge-sale-inline">-{{ $product->discountPercent() }}%</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="compare-label">Availability</th>
                        @foreach($products as $product)
                            <td class="{{ $product->isAvailable() ? 'text-success' : 'text-danger' }} fw-semibold">
                                {{ $product->isAvailable() ? 'In Stock' : 'Out of Stock' }}
                            </td>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="compare-label">Delivery</th>
                        @foreach($products as $product)
                            <td>{{ $product->deliveryEstimate() }}</td>
                        @endforeach
                    </tr>
                    <tr>
                        <th class="compare-label">Warranty</th>
                        @foreach($products as $product)
                            <td>{{ $product->warrantyLabel() }}</td>
                        @endforeach
                    </tr>
                    @foreach($specRows as $label => $values)
                        <tr>
                            <th class="compare-label">{{ $label }}</th>
                            @foreach($values as $value)
                                <td>{{ $value }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                    <tr>
                        <th class="compare-label">Actions</th>
                        @foreach($products as $product)
                            <td>
                                <div class="d-grid gap-2">
                                    @if($product->isAvailable())
                                        <form action="{{ route('cart.add', $product) }}" method="POST">
                                            @csrf
                                            <button type="submit" class="btn btn-primary btn-sm w-100">Add to Cart</button>
                                        </form>
                                    @endif
                                    <form action="{{ route('wishlist.toggle', $product) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-outline-primary btn-sm w-100">Save to wishlist</button>
                                    </form>
                                    <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-outline-secondary btn-sm">Request quote</a>
                                </div>
                            </td>
                        @endforeach
                    </tr>
                </tbody>
            </table>
        </div>
    @elseif($products->count() === 1)
        <div class="checkout-card">
            <p class="mb-3">Add at least one more product to see a side-by-side comparison.</p>
            <div class="row g-4">
                <div class="col-6 col-md-4 col-lg-3">
                    @include('partials.product-card', ['product' => $products->first()])
                </div>
            </div>
            <a href="{{ route('shop.index') }}" class="btn btn-primary mt-4">Find another product</a>
        </div>
    @else
        <div class="text-center py-5 checkout-card">
            <h2 class="h4 fw-bold mb-2">No products to compare yet</h2>
            <p class="text-muted mb-4">Use the compare button on product cards to shortlist up to {{ \App\Services\CompareService::MAX_ITEMS }} items — handy for matching switches, laptops or cameras.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-primary">Browse products</a>
        </div>
    @endif
</div>
@endsection
