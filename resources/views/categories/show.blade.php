@extends('layouts.app')

@section('title', $category->meta_title ?: $category->name.' | Urban Focus')
@section('meta_description', $category->meta_description ?: 'Browse '.$category->name.' at Urban Focus.')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('shop.index') }}">Shop</a></li>
            <li class="breadcrumb-item active">{{ $category->name }}</li>
        </ol>
    </nav>

    <h1 class="h2 fw-bold mb-2">{{ $category->name }}</h1>
    @if($category->description)<p class="text-muted mb-4">{{ $category->description }}</p>@endif

    @if($products->count())
        <div class="row g-4">
            @foreach($products as $product)
                <div class="col-6 col-md-4 col-lg-3">@include('partials.product-card', ['product' => $product])</div>
            @endforeach
        </div>
        <div class="mt-4">{{ $products->links() }}</div>
    @else
        <p class="text-muted">No products in this category yet.</p>
    @endif
</div>
@endsection
