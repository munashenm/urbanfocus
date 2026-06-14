@extends('layouts.app')

@section('title', $category->meta_title ?: $category->name.' | Urban Focus')
@section('meta_description', $category->seoDescription())
@section('canonical', $canonicalUrl)
@if(request()->hasAny(['brand', 'price_min', 'price_max']) || (request('sort') && request('sort') !== 'newest'))
@section('meta_robots', 'noindex, follow')
@endif

@include('partials.pagination-seo')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('shop.index') }}">Shop</a></li>
            @foreach($category->breadcrumbChain() as $index => $crumb)
                @if($index === count($category->breadcrumbChain()) - 1)
                    <li class="breadcrumb-item active">{{ $crumb['name'] }}</li>
                @else
                    <li class="breadcrumb-item"><a href="{{ $crumb['category']->url() }}">{{ $crumb['name'] }}</a></li>
                @endif
            @endforeach
        </ol>
    </nav>

    <div class="category-hero mb-4">
        <h1 class="h2 fw-bold mb-2">{{ $category->name }}</h1>
        @if($category->description)<p class="text-muted mb-0">{{ $category->description }}</p>@endif
    </div>

    @if($siblingCategories->count() > 1)
        <nav class="category-sibling-nav mb-4" aria-label="Related subcategories in {{ $category->parent->name }}">
            <p class="small text-muted mb-2">Browse {{ $category->parent->name }}</p>
            <div class="category-sibling-scroll d-flex gap-2 pb-1">
                @foreach($siblingCategories as $sibling)
                    <a href="{{ $sibling->url() }}" class="category-sibling-pill {{ $sibling->id === $category->id ? 'is-active' : '' }}">{{ $sibling->name }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    <div class="row g-4">
        <aside class="col-lg-3">
            @if($subcategories->count())
                @include('partials.category-subnav', ['title' => 'Subcategories', 'categories' => $subcategories])
            @endif

            @include('partials.shop-filters', [
                'filterAction' => $category->url(),
                'showCategoryFilter' => false,
                'categories' => collect(),
                'embedded' => true,
            ])
        </aside>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
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
                    <p class="text-muted">No products in this category yet.</p>
                    <a href="{{ route('b2b.source') }}" class="btn btn-primary">Request Product Sourcing</a>
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
    "name": {{ json_encode($category->name) }},
    "description": {{ json_encode($category->description ?: 'Browse '.$category->name.' at Urban Focus') }},
    "url": "{{ $canonicalUrl }}"
}
</script>
@if(!empty($breadcrumbSchema))
<script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT) !!}</script>
@endif
@endpush
