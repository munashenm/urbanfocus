@extends('layouts.app')

@section('title', 'Shop IT Products | Urban Focus')
@section('meta_description', seo_meta_description('Browse laptops, desktops, networking, storage and software from Urban Focus.', ['type' => 'category', 'name' => 'IT products']))
@if(request()->hasAny(['q', 'category', 'brand', 'deals', 'price_min', 'price_max']) || (request('sort') && ! app(\App\Services\CatalogBrowseService::class)->isDefaultSort(request())))
@section('meta_robots', 'noindex, follow')
@endif

@include('partials.pagination-seo')

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
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                <h1 class="h3 mb-0">
                    @if(request('q'))Results for "{{ request('q') }}"
                    @elseif(request('deals'))Deals &amp; Specials
                    @else All Products @endif
                </h1>
                <div class="d-flex align-items-center gap-2">
                    <form method="GET" action="{{ route('shop.index') }}" class="d-lg-none">
                        @foreach(request()->except(['sort', 'page']) as $key => $val)
                            @if(is_string($val))<input type="hidden" name="{{ $key }}" value="{{ $val }}">@endif
                        @endforeach
                        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Sort products">
                            @include('partials.sort-options')
                        </select>
                    </form>
                    <span class="text-muted small">{{ $products->total() }} products</span>
                </div>
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
@if(!empty($collectionPageSchema))
<script type="application/ld+json">{!! json_encode($collectionPageSchema, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_PRETTY_PRINT) !!}</script>
@endif
@if(!empty($breadcrumbSchema))
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@endif
@endpush
