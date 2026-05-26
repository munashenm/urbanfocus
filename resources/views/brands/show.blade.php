@extends('layouts.app')

@section('title', $brand->name.' Products | Urban Focus')
@section('meta_description', $brand->seoDescription())

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('brands.index') }}">Brands</a></li>
            <li class="breadcrumb-item active">{{ $brand->name }}</li>
        </ol>
    </nav>

    <div class="brand-hero d-flex flex-wrap align-items-center gap-3 mb-4">
        @if($brand->logo)
            <img src="{{ asset($brand->logo) }}" alt="{{ $brand->name }}" class="brand-hero-logo" height="48" loading="lazy">
        @endif
        <div>
            <h1 class="h2 fw-bold mb-1">{{ $brand->name }}</h1>
            <p class="text-muted mb-0">Genuine {{ $brand->name }} products supplied by Urban Focus.</p>
        </div>
    </div>

    <div class="row g-4">
        @include('partials.shop-filters', ['showCategoryFilter' => true, 'showBrandFilter' => false, 'filterAction' => route('brands.show', $brand)])

        <div class="col-lg-9">
            @if($products->count())
                <div class="row g-4">
                    @foreach($products as $product)
                        <div class="col-6 col-md-4">@include('partials.product-card', ['product' => $product])</div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $products->links() }}</div>
            @else
                <p class="text-muted">No {{ $brand->name }} products listed yet. <a href="{{ route('b2b.source') }}">Request sourcing</a>.</p>
            @endif
        </div>
    </div>
</div>
@endsection
