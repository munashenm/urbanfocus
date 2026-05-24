@extends('layouts.app')

@section('title', 'Page Not Found | Urban Focus')

@section('content')
<div class="container py-5 text-center">
    <div class="checkout-card mx-auto" style="max-width:520px">
        <div class="display-1 fw-bold text-navy mb-2">404</div>
        <h1 class="h4 fw-bold mb-3">Page not found</h1>
        <p class="text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
        <div class="d-flex flex-wrap gap-2 justify-content-center">
            <a href="{{ route('home') }}" class="btn btn-primary">Go Home</a>
            <a href="{{ route('shop.index') }}" class="btn btn-outline-primary">Browse Shop</a>
            <a href="{{ route('contact') }}" class="btn btn-outline-secondary">Contact Us</a>
        </div>
    </div>
</div>
@endsection
