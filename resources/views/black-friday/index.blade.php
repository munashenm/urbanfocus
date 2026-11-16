@extends('layouts.app')

@section('title', 'Black Friday Specials | Urban Focus')
@section('meta_description', 'Black Friday specials on laptops, networking, CCTV and IT products from Urban Focus. Sale prices include VAT and return to the normal price when the offer ends.')
@section('canonical', route('black-friday'))

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Black Friday</li>
        </ol>
    </nav>

    <h1 class="h2 mb-2">Black Friday Specials</h1>
    <p class="text-muted mb-4">Prices include VAT. Each product returns to its normal price when its sale ends.</p>

    <form method="GET" action="{{ route('black-friday') }}" class="row g-2 align-items-end mb-4">
        <div class="col-sm-6 col-md-4">
            <label for="bf-category" class="form-label">Category</label>
            <select id="bf-category" name="category" class="form-select">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->slug }}" @selected(optional($activeCategory)->id === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-sm-6 col-md-4">
            <label for="bf-sort" class="form-label">Sort</label>
            <select id="bf-sort" name="sort" class="form-select">
                <option value="saving" @selected(request('sort', 'saving') === 'saving')>Biggest saving</option>
                <option value="price_asc" @selected(request('sort') === 'price_asc')>Price: low to high</option>
                <option value="price_desc" @selected(request('sort') === 'price_desc')>Price: high to low</option>
            </select>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary">Apply</button>
        </div>
    </form>

    @if($products->count())
        <div class="row g-4">
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
    @else
        <div class="text-center py-5">
            <p class="mb-3">No specials right now.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-primary">Browse the shop</a>
        </div>
    @endif
</div>
@endsection
