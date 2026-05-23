@extends('layouts.app')

@section('title', $product->seoTitle())
@section('meta_description', $product->seoDescription())
@section('meta_keywords', $product->meta_keywords)
@section('canonical', route('products.show', $product))

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            @if($product->category)
                <li class="breadcrumb-item"><a href="{{ route('categories.show', $product->category) }}">{{ $product->category->name }}</a></li>
            @endif
            <li class="breadcrumb-item active">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="row g-5">
        <div class="col-lg-6">
            <div class="product-detail-image">
                @if($product->primary_image_url)
                    <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" width="500" height="500">
                @else
                    <div class="py-5 text-muted">No image available</div>
                @endif
            </div>
        </div>
        <div class="col-lg-6">
            @if($product->brand)<div class="text-muted small text-uppercase">{{ $product->brand }}</div>@endif
            <h1 class="h2 fw-bold">{{ $product->name }}</h1>
            @if($product->sku)<p class="text-muted small">SKU: {{ $product->sku }}</p>@endif

            <div class="my-3">
                @if($product->is_on_sale)
                    <span class="price-old h5">R {{ number_format($product->price, 2) }}</span>
                @endif
                <span class="price-current h3">R {{ number_format($product->effective_price, 2) }}</span>
                <span class="text-muted small"> incl. VAT</span>
            </div>

            <p class="{{ $product->isAvailable() ? 'text-success' : 'text-danger' }} fw-semibold">
                {{ $product->isAvailable() ? 'In Stock' : 'Out of Stock' }}
            </p>

            @if($product->short_description)
                <p>{{ $product->short_description }}</p>
            @endif

            @if($product->isAvailable())
                <form action="{{ route('cart.add', $product) }}" method="POST" class="d-flex gap-2 align-items-center my-4">
                    @csrf
                    <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock_quantity ?: 99 }}" class="form-control" style="width:80px">
                    <button type="submit" class="btn btn-primary btn-lg flex-grow-1">Add to Cart</button>
                </form>
            @endif

            <div class="border-top pt-3 small text-muted">
                <p class="mb-1">Free shipping on orders over R {{ number_format(config('shipping.free_threshold'), 0) }}</p>
                <p class="mb-0">Questions? Call <a href="tel:0875501813">087 550 1813</a></p>
            </div>
        </div>
    </div>

    @if($product->description)
    <div class="row mt-5">
        <div class="col-lg-8">
            <h2 class="h4 mb-3">Product Description</h2>
            <div class="product-description">{!! $product->description !!}</div>
        </div>
    </div>
    @endif

    @if($relatedProducts->count())
    <section class="mt-5 pt-4 border-top">
        <h2 class="section-title">Related Products</h2>
        <div class="row g-4">
            @foreach($relatedProducts as $related)
                <div class="col-6 col-md-3">@include('partials.product-card', ['product' => $related])</div>
            @endforeach
        </div>
    </section>
    @endif
</div>
@endsection

@push('schema')
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@endpush
