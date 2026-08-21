@php
    $wishlist = app(\App\Services\WishlistService::class);
    $compare = app(\App\Services\CompareService::class);
    $inWishlist = $wishlist->has($product->id);
    $inCompare = $compare->has($product->id);
    $discount = $product->discountPercent();
@endphp
<div class="product-card h-100{{ $product->isAvailable() ? '' : ' is-unavailable' }}">
    <div class="product-card-image-wrap">
        <a href="{{ route('products.show', $product) }}" class="product-card-image d-block">
            <img src="{{ $product->display_image_url }}" alt="{{ $product->imageAlt() }}" loading="lazy" width="300" height="300">
            @if($product->is_on_sale || (!empty($product->is_deal)))
                <span class="badge-sale">
                    @if($discount)
                        -{{ $discount }}%
                    @else
                        {{ $product->deal_label ?: 'Sale' }}
                    @endif
                </span>
            @endif
        </a>
        <div class="product-card-tools">
            <form action="{{ route('wishlist.toggle', $product) }}" method="POST">
                @csrf
                <button type="submit" class="product-tool-btn {{ $inWishlist ? 'is-active' : '' }}" aria-pressed="{{ $inWishlist ? 'true' : 'false' }}" title="{{ $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' }}" aria-label="{{ $inWishlist ? 'Remove from wishlist' : 'Add to wishlist' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="{{ $inWishlist ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="1.6" viewBox="0 0 16 16" aria-hidden="true"><path d="m8 13.5-5.2-5.05A3.3 3.3 0 1 1 8 3.55a3.3 3.3 0 1 1 5.2 4.9L8 13.5z"/></svg>
                </button>
            </form>
            <form action="{{ route('compare.toggle', $product) }}" method="POST">
                @csrf
                <button type="submit" class="product-tool-btn {{ $inCompare ? 'is-active' : '' }}" aria-pressed="{{ $inCompare ? 'true' : 'false' }}" title="{{ $inCompare ? 'Remove from compare' : 'Compare this product' }}" aria-label="{{ $inCompare ? 'Remove from compare' : 'Compare this product' }}">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M4 2v12H2V2h2zm10 0v12h-2V2h2zM9.5 4v8H6.5V4h3z"/></svg>
                </button>
            </form>
        </div>
    </div>
    <div class="product-card-body">
        <div class="product-card-meta">
            @if($product->brand)
                <div class="product-brand">{{ $product->brand }}</div>
            @endif
            @if($product->sku)
                <div class="product-sku small text-muted">SKU: {{ $product->sku }}</div>
            @endif
        </div>
        <h3 class="product-title">
            <a href="{{ route('products.show', $product) }}" title="{{ $product->name }}">{{ $product->name }}</a>
        </h3>
        <div class="product-card-footer">
            <div class="product-price">
                @if($product->is_on_sale)
                    <span class="price-old">R {{ number_format($product->price, 2) }}</span>
                    <span class="price-current">R {{ number_format($product->effective_price, 2) }}</span>
                @else
                    <span class="price-current">R {{ number_format($product->effective_price, 2) }}</span>
                @endif
            </div>
            <div class="product-stock {{ $product->isAvailable() ? 'in-stock' : 'out-stock' }}">
                {{ $product->isAvailable() ? 'In Stock' : 'Out of Stock' }}
            </div>
            @if($product->isAvailable())
                <form action="{{ route('cart.add', $product) }}" method="POST" class="product-card-action">
                    @csrf
                    <button type="submit" class="btn btn-primary w-100 btn-sm">Add to Cart</button>
                </form>
            @else
                <div class="product-card-action">
                    <button type="button" class="btn btn-outline-secondary w-100 btn-sm" disabled>Out of Stock</button>
                </div>
            @endif
        </div>
    </div>
</div>
