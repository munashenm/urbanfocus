@extends('layouts.app')

@section('title', $page['title'])
@section('meta_description', $page['description'])
@section('canonical', route('solutions.show', $slug))
@section('og_title', $page['title'])
@section('og_description', $page['description'])

@push('schema')
    <script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->breadcrumbSchema([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'Solutions', 'url' => route('solutions.index')],
                ['name' => $page['h1'], 'url' => route('solutions.show', $slug)],
    ]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @if(!empty($faqSchema))
        <script type="application/ld+json">{!! json_encode($faqSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endif
@endpush

@section('content')
<div class="page-hero">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb breadcrumb-light mb-2">
                <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
                <li class="breadcrumb-item"><a href="{{ route('solutions.index') }}">Solutions</a></li>
                <li class="breadcrumb-item active" aria-current="page">{{ $page['h1'] }}</li>
            </ol>
        </nav>
        <h1 class="h2 fw-bold mb-2">{{ $page['h1'] }}</h1>
        <p class="mb-0 opacity-90 lead">{{ $page['intro'] }}</p>
    </div>
</div>

<div class="container py-5">
    <div class="row g-5">
        <div class="col-lg-8">
            @foreach($page['body'] ?? [] as $paragraph)
                <p class="text-muted">{{ $paragraph }}</p>
            @endforeach

            @if($categories->isNotEmpty())
                <h2 class="h4 fw-bold mt-4 mb-3">Browse categories</h2>
                <ul class="list-unstyled row g-2">
                    @foreach($categories as $category)
                        <li class="col-md-6">
                            <a href="{{ $category->url() }}" class="text-decoration-none">{{ $category->name }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($brands->isNotEmpty())
                <h2 class="h4 fw-bold mt-4 mb-3">Featured brands</h2>
                <ul class="list-unstyled row g-2">
                    @foreach($brands as $brand)
                        <li class="col-md-6">
                            <a href="{{ route('brands.show', $brand) }}" class="text-decoration-none">{{ $brand->name }}</a>
                        </li>
                    @endforeach
                </ul>
            @endif

            @if(!empty($page['use_cases']))
                <h2 class="h4 fw-bold mt-4 mb-3">Business use cases</h2>
                <ul class="text-muted">
                    @foreach($page['use_cases'] as $useCase)
                        <li>{{ $useCase }}</li>
                    @endforeach
                </ul>
            @endif

            @if(!empty($page['procurement']))
                <h2 class="h4 fw-bold mt-4 mb-3">Procurement</h2>
                <p class="text-muted">{{ $page['procurement'] }}</p>
            @else
                <h2 class="h4 fw-bold mt-4 mb-3">Procurement</h2>
                <p class="text-muted">Urban Focus supports VAT-compliant invoicing, nationwide delivery and formal quotations for bulk, corporate and tender orders.</p>
            @endif
            @if(!empty($page['links']))
                <h2 class="h4 fw-bold mt-4 mb-3">Next steps</h2>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($page['links'] as $link)
                        <a href="{{ route($link['route']) }}" class="btn btn-outline-primary btn-sm">{{ $link['label'] }}</a>
                    @endforeach
                </div>
            @endif

            @if($faqs !== [])
                <h2 class="h4 fw-bold mt-5 mb-3">Frequently asked questions</h2>
                <div class="accordion" id="landingFaq">
                    @foreach($faqs as $index => $faq)
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button {{ $index > 0 ? 'collapsed' : '' }}" type="button" data-bs-toggle="collapse" data-bs-target="#faq-{{ $index }}">
                                    {{ $faq['question'] }}
                                </button>
                            </h3>
                            <div id="faq-{{ $index }}" class="accordion-collapse collapse {{ $index === 0 ? 'show' : '' }}" data-bs-parent="#landingFaq">
                                <div class="accordion-body text-muted">{{ $faq['answer'] }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="col-lg-4">
            @include('partials.corporate-procurement-cta', ['compact' => true])
        </div>
    </div>

    @if($featuredProducts->isNotEmpty())
        <section class="mt-5 pt-4 border-top">
            <h2 class="h4 fw-bold mb-4">Featured products</h2>
            <div class="row g-4">
                @foreach($featuredProducts as $product)
                    <div class="col-6 col-md-3">@include('partials.product-card', ['product' => $product])</div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection
