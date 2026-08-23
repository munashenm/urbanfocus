@php
    $discount = $product->discountPercent();
@endphp
<div class="product-card h-100{{ $product->isAvailable() ? '' : ' is-unavailable' }}">
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
                {{ $product->availabilityLabel() }}
            </div>
            @if($product->isQuoteOnly())
                <div class="product-card-action">
                    <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-primary w-100 btn-sm">{{ $product->availabilityKey() === 'contact_licensing' ? 'Contact for licensing' : 'Request a Quote' }}</a>
                </div>
            @elseif($product->isAvailable())
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
