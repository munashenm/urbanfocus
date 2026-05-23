<div class="product-card h-100">
    <a href="{{ route('products.show', $product) }}" class="product-card-image d-block">
        @if($product->primary_image_url)
            <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" loading="lazy" width="300" height="300">
        @else
            <div class="product-placeholder d-flex align-items-center justify-content-center">No image</div>
        @endif
        @if($product->is_on_sale)
            <span class="badge-sale">Sale</span>
        @endif
    </a>
    <div class="product-card-body">
        @if($product->brand)
            <div class="product-brand">{{ $product->brand }}</div>
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
