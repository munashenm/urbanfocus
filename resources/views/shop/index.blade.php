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
        <aside class="col-lg-3">
            <div class="filter-sidebar">
                <h6 class="fw-bold mb-3">Filters</h6>
                <form method="GET" action="{{ route('shop.index') }}">
                    @if(request('q'))<input type="hidden" name="q" value="{{ request('q') }}">@endif
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Category</label>
                        <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($brands->count())
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Brand</label>
                        <select name="brand" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All Brands</option>
                            @foreach($brands as $b)
                                <option value="{{ $b }}" @selected(request('brand') === $b)>{{ $b }}</option>
                            @endforeach
                        </select>
                    </div>
                    @endif
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="in_stock" value="1" id="inStock" @checked(request('in_stock')) onchange="this.form.submit()">
                        <label class="form-check-label small" for="inStock">In stock only</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Sort by</label>
                        <select name="sort" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="newest" @selected(request('sort', 'newest') === 'newest')>Newest</option>
                            <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: Low to High</option>
                            <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: High to Low</option>
                            <option value="name" @selected(request('sort') === 'name')>Name</option>
                        </select>
                    </div>
                </form>
            </div>
        </aside>

        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">@if(request('q'))Results for "{{ request('q') }}"@else All Products @endif</h1>
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
                    <a href="{{ route('shop.index') }}" class="btn btn-primary">Browse All Products</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
