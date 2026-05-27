@extends('layouts.app')

@section('title', config('blog.index_title'))
@section('meta_description', config('blog.index_description'))
@if(!empty($pagination['canonical']))
@section('canonical', $pagination['canonical'])
@endif
@if(!empty($pagination['prev']))
    @push('head')<link rel="prev" href="{{ $pagination['prev'] }}">@endpush
@endif
@if(!empty($pagination['next']))
    @push('head')<link rel="next" href="{{ $pagination['next'] }}">@endpush
@endif

@section('content')
<div class="page-hero">
    <div class="container">
        <h1 class="h2 fw-bold mb-2">IT Insights &amp; Guides</h1>
        <p class="mb-0 opacity-75">Buying guides, networking tips, CCTV advice and procurement insights for South African businesses.</p>
    </div>
</div>

<div class="container py-5">
    @if($categories !== [])
        <nav class="blog-category-nav mb-4" aria-label="Blog categories">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('blog.index') }}" class="btn btn-sm {{ empty($activeCategory) ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
                @foreach($categories as $key => $meta)
                    <a href="{{ route('blog.category', $key) }}" class="btn btn-sm {{ $activeCategory === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $meta['label'] }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    @if($featured && ! $activeCategory && $articles->onFirstPage())
        <section class="mb-5">
            @include('partials.article-card', ['article' => $featured, 'featured' => true])
        </section>
    @endif

    @if($articles->count())
        <div class="row g-4">
            @foreach($articles as $article)
                <div class="col-md-6 col-lg-4">
                    @include('partials.article-card', ['article' => $article])
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $articles->links() }}</div>
    @elseif(! $featured)
        <div class="text-center py-5">
            <p class="text-muted mb-3">
                @if($activeCategory)
                    No articles in this category yet.
                @else
                    Articles coming soon — we're preparing guides on hardware, networking and software licensing.
                @endif
            </p>
            <a href="{{ route('shop.index') }}" class="btn btn-primary me-2">Browse Products</a>
            <a href="{{ route('b2b.quote') }}" class="btn btn-outline-primary">Request a Quote</a>
        </div>
    @endif

    <section class="blog-cta-panel mt-5">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <h2 class="h5 fw-bold mb-1">Need hardware for your project?</h2>
                <p class="text-muted mb-0 small">Urban Focus supplies networking, laptops, CCTV and enterprise IT across South Africa with VAT invoices and bulk quotes.</p>
            </div>
            <div class="col-lg-4 d-flex flex-wrap gap-2 justify-content-lg-end">
                <a href="{{ route('b2b.quote') }}" class="btn btn-primary btn-sm">Request a Quote</a>
                <a href="{{ route('shop.index') }}" class="btn btn-outline-primary btn-sm">Shop Products</a>
            </div>
        </div>
    </section>
</div>
@endsection

@push('schema')
<script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->breadcrumbSchema([
    ['name' => 'Home', 'url' => route('home')],
    ['name' => 'Blog', 'url' => route('blog.index')],
]), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
