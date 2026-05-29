@extends('layouts.app')

@section('title', $article->seoTitle())
@section('meta_description', $article->seoDescription())
@section('canonical', route('blog.show', $article))
@section('og_type', 'article')
@section('og_image', $article->displayImageUrl())
@section('twitter_card', 'summary_large_image')

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Blog</a></li>
            @if($article->categoryLabel())
                <li class="breadcrumb-item"><a href="{{ route('blog.category', $article->categoryKey()) }}">{{ $article->categoryLabel() }}</a></li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ \Illuminate\Support\Str::limit($article->title, 60) }}</li>
        </ol>
    </nav>

    <article class="row g-5">
        <div class="col-lg-8">
            <header class="mb-4">
                <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                    @if($article->categoryLabel())
                        <a href="{{ route('blog.category', $article->categoryKey()) }}" class="article-category-badge text-decoration-none">{{ $article->categoryLabel() }}</a>
                    @endif
                    <time class="text-muted small" datetime="{{ $article->published_at?->toAtomString() }}">{{ $article->published_at?->format('d F Y') }}</time>
                    <span class="text-muted small">{{ $article->readingTimeMinutes() }} min read</span>
                </div>
                <h1 class="h2 fw-bold">{{ $article->title }}</h1>
                @if($article->excerpt)
                    <p class="lead text-muted mt-3">{{ $article->excerpt }}</p>
                @endif
                <p class="small text-muted mb-0">
                    By <a href="{{ $article->author ? route('blog.author', $article->author) : route('blog.index') }}">{{ $article->authorName() }}</a>
                </p>
                @if($article->relationLoaded('tags') && $article->tags->isNotEmpty())
                    <div class="d-flex flex-wrap gap-1 mt-2">
                        @foreach($article->tags as $tag)
                            <a href="{{ route('blog.tag', $tag) }}" class="badge bg-light text-dark text-decoration-none">{{ $tag->name }}</a>
                        @endforeach
                    </div>
                @endif
            </header>

            <div class="article-featured-image mb-4">
                <img src="{{ $article->displayImageUrl() }}" alt="{{ $article->title }}" width="960" height="540" loading="eager">
            </div>

            @include('blog.partials.toc', ['tocItems' => $tocItems])

            <div class="article-content">
                {!! clean_html($content) !!}
            </div>

            @include('blog.partials.faq', ['article' => $article])

            @if($article->source_url)
                <p class="small text-muted mt-4 pt-3 border-top">
                    Originally published by {{ $article->source_name ?: 'external source' }} —
                    <a href="{{ $article->source_url }}" target="_blank" rel="noopener noreferrer">view original</a>
                </p>
            @endif
        </div>

        <aside class="col-lg-4">
            @if($categoryCta)
                <div class="blog-sidebar-card mb-4">
                    <h2 class="h6 fw-bold mb-2">Shop {{ $categoryCta['label'] ?? 'products' }}</h2>
                    <p class="small text-muted mb-3">{{ $categoryCta['description'] ?? 'Browse genuine IT products from Urban Focus with nationwide delivery.' }}</p>
                    <a href="{{ route($categoryCta['cta_route'], $categoryCta['cta_params'] ?? []) }}" class="btn btn-primary w-100 mb-2">{{ $categoryCta['cta_label'] ?? 'Browse products' }}</a>
                    <a href="{{ route('contact') }}" class="btn btn-outline-secondary w-100 btn-sm">Contact sales</a>
                </div>
            @endif

            @if(count($tocItems) >= 2)
                <div class="blog-sidebar-card mb-4 d-none d-lg-block">
                    <h2 class="h6 fw-bold mb-2">Contents</h2>
                    @include('blog.partials.toc', ['tocItems' => $tocItems])
                </div>
            @endif

            <div class="blog-sidebar-card">
                <h2 class="h6 fw-bold mb-2">Corporate procurement</h2>
                <p class="small text-muted mb-3">VAT invoices, bulk quotes and RFQ support for businesses across South Africa.</p>
                <a href="{{ route('b2b.quote') }}" class="btn btn-outline-primary w-100 btn-sm">Request a Quote</a>
            </div>
        </aside>
    </article>

    @if($related->count())
        <section class="mt-5 pt-4 border-top">
            <h2 class="h5 fw-bold mb-4">Related articles</h2>
            <div class="row g-4">
                @foreach($related as $item)
                    <div class="col-md-4">
                        @include('partials.article-card', ['article' => $item])
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('schema')
<script type="application/ld+json">{!! json_encode($articleSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
@if($article->faqList() !== [])
<script type="application/ld+json">{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'FAQPage',
    'mainEntity' => collect($article->faqList())->map(fn ($faq) => [
        '@type' => 'Question',
        'name' => $faq['question'],
        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
    ])->values()->all(),
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endif
<script type="application/ld+json">{!! json_encode(app(\App\Services\SeoService::class)->breadcrumbSchema(array_values(array_filter([
    ['name' => 'Home', 'url' => route('home')],
    ['name' => 'Blog', 'url' => route('blog.index')],
    $article->categoryLabel() ? ['name' => $article->categoryLabel(), 'url' => route('blog.category', $article->categoryKey())] : null,
    ['name' => $article->title, 'url' => route('blog.show', $article)],
]))), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
@endpush
