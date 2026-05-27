@extends('layouts.app')

@section('title', $brand->seoTitle())
@section('meta_description', $brand->seoDescription())
@section('canonical', $pagination['canonical'] ?? route('brands.show', $brand))

@if(!empty($pagination['prev']))
    @push('head')<link rel="prev" href="{{ $pagination['prev'] }}">@endpush
@endif
@if(!empty($pagination['next']))
    @push('head')<link rel="next" href="{{ $pagination['next'] }}">@endpush
@endif

@push('schema')
    <script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->breadcrumbSchema([
        ['name' => 'Home', 'url' => route('home')],
        ['name' => 'Brands', 'url' => route('brands.index')],
        ['name' => $brand->name, 'url' => route('brands.show', $brand)],
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if(!empty($faqSchema))
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endpush

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
            <img src="{{ asset($brand->logo) }}" alt="{{ $brand->name }} logo" class="brand-hero-logo" width="120" height="36" loading="lazy">
        @endif
        <div>
            <h1 class="h2 fw-bold mb-1">{{ $brandSeo['headline'] ?? $brand->name }}</h1>
            <p class="text-muted mb-0">{{ $brandSeo['intro'] ?? ('Genuine '.$brand->name.' products supplied by Urban Focus with VAT invoices and nationwide delivery.') }}</p>
        </div>
    </div>

    @if(!empty($brandSeo['sections']))
        <div class="row g-4 mb-4">
            @foreach($brandSeo['sections'] as $section)
                <div class="col-md-6">
                    <div class="card h-100 border-0 bg-light">
                        <div class="card-body">
                            <h2 class="h5 fw-bold">{{ $section['heading'] }}</h2>
                            <p class="text-muted mb-0 small">{{ $section['body'] }}</p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($linkCategories->isNotEmpty())
        <div class="mb-4">
            <h2 class="h6 fw-bold text-uppercase text-muted mb-2">Shop by category</h2>
            <div class="d-flex flex-wrap gap-2">
                @foreach($linkCategories as $category)
                    <a href="{{ route('categories.show', $category) }}?brand={{ urlencode($brand->name) }}" class="btn btn-outline-secondary btn-sm">{{ $category->name }}</a>
                @endforeach
            </div>
        </div>
    @endif

    @if($featuredProducts->isNotEmpty() && $products->currentPage() === 1)
        <section class="mb-5">
            <h2 class="h5 fw-bold mb-3">Featured {{ $brand->name }} products</h2>
            <div class="row g-4">
                @foreach($featuredProducts as $product)
                    <div class="col-6 col-md-3">@include('partials.product-card', ['product' => $product])</div>
                @endforeach
            </div>
        </section>
    @endif

    <div class="row g-4">
        @include('partials.shop-filters', ['showCategoryFilter' => true, 'showBrandFilter' => false, 'filterAction' => route('brands.show', $brand)])

        <div class="col-lg-9">
            <h2 class="h5 fw-bold mb-3">All {{ $brand->name }} products</h2>
            @if($products->count())
                <div class="row g-4">
                    @foreach($products as $product)
                        <div class="col-6 col-md-4">@include('partials.product-card', ['product' => $product])</div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $products->links() }}</div>
            @else
                <p class="text-muted">No {{ $brand->name }} products listed yet. <a href="{{ route('b2b.source') }}">Request sourcing</a> or <a href="{{ route('b2b.quote') }}">request a quote</a>.</p>
            @endif
        </div>
    </div>

    @if($faqs !== [])
        <section class="mt-5 pt-4 border-top">
            <h2 class="h5 fw-bold mb-3">{{ $brand->name }} — frequently asked questions</h2>
            <div class="accordion" id="brandFaq">
                @foreach($faqs as $index => $faq)
                    <div class="accordion-item">
                        <h3 class="accordion-header">
                            <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#brand-faq-{{ $index }}">
                                {{ $faq['question'] }}
                            </button>
                        </h3>
                        <div id="brand-faq-{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" data-bs-parent="#brandFaq">
                            <div class="accordion-body text-muted">{{ $faq['answer'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
