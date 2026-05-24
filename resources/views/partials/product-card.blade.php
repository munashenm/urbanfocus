<div class="product-card h-100">
    <a href="{{ route('products.show', $product) }}" class="product-card-image d-block">
        <img src="{{ $product->display_image_url }}" alt="{{ $product->name }}" loading="lazy" width="300" height="300">
        @if($product->is_on_sale || (!empty($product->is_deal)))
            <span class="badge-sale">{{ $product->deal_label ?: 'Sale' }}</span>
        @endif
    </a>
    <div class="product-card-body">
        @if($product->brand)
            <div class="product-brand">{{ $product->brand }}</div>
        @endif
        @if($product->sku)
            <div class="product-sku small text-muted">SKU: {{ $product->sku }}</div>
        @endif
        <h3 class="product-title">
            <a href="{{ route('products.show', $product) }}">{{ $product->name }}</a>
        </h3>
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
            <form action="{{ route('cart.add', $product) }}" method="POST" class="mt-3">
                @csrf
                <button type="submit" class="btn btn-primary w-100 btn-sm">Add to Cart</button>
            </form>
        @endif
    </div>
</div>
