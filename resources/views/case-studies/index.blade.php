@extends('layouts.app')

@section('title', config('case_studies.index_title'))
@section('meta_description', config('case_studies.index_description'))
@section('canonical', route('case-studies.index'))

@push('schema')
    <script type="application/ld+json">{!! json_encode($breadcrumbSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush

@section('content')
<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-light mb-2">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Case Studies</li>
            </ol>
        </nav>
        <h1 class="h2 fw-bold mb-2">Case studies</h1>
        <p class="mb-0 opacity-90">Selected IT infrastructure and procurement work. We publish case studies only when clients authorise them.</p>
    </div>
</div>

<div class="container py-5">
    @if(count($items))
        <div class="row g-4">
            @foreach($items as $item)
                <div class="col-md-6 col-lg-4">
                    <a href="{{ route('case-studies.show', $item['slug']) }}" class="card h-100 border-0 shadow-sm text-decoration-none text-dark">
                        <div class="card-body">
                            <h2 class="h5 fw-bold">{{ $item['title'] }}</h2>
                            @if(!empty($item['industry']))
                                <p class="small text-muted mb-0">{{ $item['industry'] }}</p>
                            @endif
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    @else
        <div class="checkout-card">
            <h2 class="h5 fw-bold">Case studies coming soon</h2>
            <p class="text-muted mb-0">We do not publish client-identifiable project details without permission. If you need a comparable reference for a tender or RFQ, contact our procurement team.</p>
        </div>
    @endif

    <div class="mt-5">
        @include('partials.corporate-procurement-cta')
    </div>
</div>
@endsection
