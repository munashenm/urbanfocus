@extends('layouts.app')

@section('title', $product->seoTitle())
@section('meta_description', $product->seoDescription())
@section('meta_keywords', $product->meta_keywords)
@section('canonical', route('products.show', $product))
@section('og_title', $product->name)
@section('og_description', $product->seoDescription())
@section('og_type', 'product')
@if($product->primary_image_url)
@section('og_image'){{ $product->primary_image_url }}@endsection
@section('twitter_card', 'summary_large_image')
@endif

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
                    <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" width="500" height="500" loading="eager">
                @else
                    <div class="py-5 text-muted">No image available</div>
                @endif
            </div>
            @if($product->images->count() > 1)
                <div class="d-flex gap-2 mt-3 flex-wrap product-thumbs">
                    @foreach($product->images as $img)
                        <img src="{{ asset('storage/'.$img->path) }}" alt="" width="72" height="72" loading="lazy" class="product-thumb">
                    @endforeach
                </div>
            @endif
        </div>
        <div class="col-lg-6">
            @if($product->brand)<div class="product-brand mb-1">{{ $product->brand }}</div>@endif
            <h1 class="h2 fw-bold">{{ $product->name }}</h1>
            <div class="d-flex flex-wrap gap-3 small text-muted mb-3">
                @if($product->sku)<span>SKU: <strong>{{ $product->sku }}</strong></span>@endif
                @if($product->model_number)<span>Model: <strong>{{ $product->model_number }}</strong></span>@endif
            </div>

            <div class="my-3">
                @if($product->is_on_sale)
                    <span class="price-old h5">R {{ number_format($product->price, 2) }}</span>
                @endif
                <span class="price-current h3">R {{ number_format($product->effective_price, 2) }}</span>
                <span class="text-muted small"> incl. VAT</span>
            </div>

            <div class="product-meta-cards mb-4">
                <div class="product-meta-card">
                    <span class="label">Availability</span>
                    <span class="{{ $product->isAvailable() ? 'text-success' : 'text-danger' }} fw-semibold">
                        {{ $product->isAvailable() ? 'In Stock' : 'Out of Stock' }}
                    </span>
                </div>
                <div class="product-meta-card">
                    <span class="label">Delivery</span>
                    <span>{{ $product->deliveryEstimate() }}</span>
                </div>
                <div class="product-meta-card">
                    <span class="label">Warranty</span>
                    <span>{{ $product->warrantyLabel() }}</span>
                </div>
            </div>

            @if($product->short_description)
                <p>{{ $product->short_description }}</p>
            @endif

            <div class="d-flex flex-wrap gap-2 my-4">
                @if($product->isAvailable())
                    <form action="{{ route('cart.add', $product) }}" method="POST" class="d-flex gap-2 align-items-center">
                        @csrf
                        <input type="number" name="quantity" value="1" min="1" max="{{ $product->stock_quantity ?: 99 }}" class="form-control" style="width:80px">
                        <button type="submit" class="btn btn-primary btn-lg">Add to Cart</button>
                    </form>
                @endif
                <a href="{{ route('b2b.quote', ['product' => $product->id]) }}" class="btn btn-outline-primary btn-lg">Request Bulk Quote</a>
            </div>

            <div class="border-top pt-3 small text-muted">
                <p class="mb-1">Free shipping on orders over R {{ number_format(config('shipping.free_threshold'), 0) }}</p>
                <p class="mb-0">Can't find this item? <a href="{{ route('b2b.source') }}">Let us source it for you</a> · Call <a href="tel:0875501813">087 550 1813</a></p>
            </div>
        </div>
    </div>

    <div class="row mt-5 g-4">
        <div class="col-lg-7">
            @if($product->description)
                <div class="checkout-card mb-4">
                    <h2 class="h5 fw-bold mb-3">Description</h2>
                    <div class="product-description">{!! $product->description !!}</div>
                </div>
            @endif
        </div>
        <div class="col-lg-5">
            @if(count($specs = $product->specificationsList()))
                <div class="checkout-card">
                    <h2 class="h5 fw-bold mb-3">Specifications</h2>
                    <table class="table table-sm spec-table mb-0">
                        <tbody>
                            @foreach($specs as $key => $value)
                                <tr><th scope="row">{{ $key }}</th><td>{{ $value }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

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

    @if($accessories->count())
    <section class="mt-5 pt-4 border-top">
        <h2 class="section-title">Accessories &amp; Add-ons</h2>
        <div class="row g-4">
            @foreach($accessories as $accessory)
                <div class="col-6 col-md-3">@include('partials.product-card', ['product' => $accessory])</div>
            @endforeach
        </div>
    </section>
    @endif
</div>
@endsection

@push('schema')
<script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@endpush
