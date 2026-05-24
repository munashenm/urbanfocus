@extends('layouts.app')

@section('title', 'Our Brands | Urban Focus')
@section('meta_description', 'Browse authorised IT brands supplied by Urban Focus — networking, laptops, security, software and more across South Africa.')

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">Our Brands</h1>
        <p class="mb-0 opacity-75">Authorised supply of leading networking, computing and security brands.</p>
    </div>
</div>

<div class="container py-5">
    @if($brands->count())
        <div class="row g-4">
            @foreach($brands as $brand)
                <div class="col-6 col-md-4 col-lg-3">
                    <a href="{{ route('brands.show', $brand) }}" class="brand-logo-card d-flex flex-column align-items-center justify-content-center h-100 p-4 text-center text-decoration-none">
                        @if($brand->logo)
                            <img src="{{ asset($brand->logo) }}" alt="{{ $brand->name }}" height="48" loading="lazy" class="mb-2">
                        @else
                            <span class="brand-logo-fallback mb-2">{{ $brand->name }}</span>
                        @endif
                        <span class="small fw-semibold text-navy">{{ $brand->name }}</span>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        @include('partials.brand-logos')
        <p class="text-center text-muted mt-4 mb-0"><a href="{{ route('shop.index') }}">Browse all products</a></p>
    @endif
</div>
@endsection
