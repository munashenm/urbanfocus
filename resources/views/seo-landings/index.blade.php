@extends('layouts.app')

@section('title', 'IT Solutions for South African Businesses | Urban Focus')
@section('meta_description', 'Corporate IT, networking, laptops, CCTV, servers and procurement solutions from Urban Focus — VAT invoices, bulk quotes and nationwide delivery.')
@section('canonical', route('solutions.index'))

@push('schema')
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-light mb-2">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Solutions</li>
            </ol>
        </nav>
        <h1 class="h2 fw-bold mb-2">IT solutions for South African businesses</h1>
        <p class="mb-0 opacity-90 lead">Procurement-focused landing pages for networking, computing, security and bulk IT supply.</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-4">
        @forelse($pages as $page)
            <div class="col-md-6 col-lg-4">
                <a href="{{ route('solutions.show', $page['slug']) }}" class="card h-100 border-0 shadow-sm text-decoration-none text-dark">
                    <div class="card-body">
                        <h2 class="h5 fw-bold">{{ $page['h1'] }}</h2>
                        @if($page['intro'])
                            <p class="small text-muted mb-0">{{ \Illuminate\Support\Str::limit($page['intro'], 140) }}</p>
                        @endif
                    </div>
                </a>
            </div>
        @empty
            <p class="text-muted">Solution pages will appear here as they are published.</p>
        @endforelse
    </div>

    <div class="mt-5">
        @include('partials.corporate-procurement-cta')
    </div>
</div>
@endsection
