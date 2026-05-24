@extends('layouts.app')

@section('title', 'Shop IT Products | Urban Focus')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item active">Shop</li>
        </ol>
    </nav>

    <div class="row g-4">
        @include('partials.shop-filters', ['showCategoryFilter' => true])

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    @if(request('q'))Results for "{{ request('q') }}"
                    @elseif(request('deals'))Deals &amp; Specials
                    @else All Products @endif
                </h1>
                <span class="text-muted small">{{ $products->total() }} products</span>
            </div>

            @if($products->count())
                <div class="row g-4">
                    @foreach($products as $product)
                        <div class="col-6 col-md-4">@include('partials.product-card', ['product' => $product])</div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $products->links() }}</div>
            @else
                <div class="text-center py-5">
                    <p class="text-muted">No products found.</p>
                    <a href="{{ route('b2b.source') }}" class="btn btn-outline-primary me-2">Source a Product</a>
                    <a href="{{ route('shop.index') }}" class="btn btn-primary">Browse All</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('schema')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "CollectionPage",
    "name": "Shop IT Products",
    "url": "{{ route('shop.index') }}"
}
</script>
@endpush
