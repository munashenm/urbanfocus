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
        <p class="mb-3 opacity-75">Buying guides, networking, software licensing, cybersecurity and procurement insights for South African businesses.</p>
        <form action="{{ route('blog.index') }}" method="GET" class="blog-search" role="search">
            <div class="input-group input-group-lg shadow-sm" style="max-width:560px;">
                <input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Search articles, guides and news…" aria-label="Search the blog">
                <button class="btn btn-primary" type="submit">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="container py-5">
    @if($categories !== [])
        <nav class="blog-category-nav mb-4" aria-label="Blog categories">
            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('blog.index') }}" class="btn btn-sm {{ empty($activeCategory) && $search === '' ? 'btn-primary' : 'btn-outline-secondary' }}">All</a>
                @foreach($categories as $key => $meta)
                    <a href="{{ route('blog.category', $key) }}" class="btn btn-sm {{ $activeCategory === $key ? 'btn-primary' : 'btn-outline-secondary' }}">{{ $meta['label'] }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    @if($search !== '')
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <h2 class="h5 mb-0">Search results for “{{ $search }}”</h2>
            <a href="{{ route('blog.index') }}" class="btn btn-sm btn-outline-secondary">Clear search</a>
        </div>
    @endif

    @if($featured && $articles->onFirstPage())
        <section class="mb-5">
            <h2 class="visually-hidden">Featured article</h2>
            @include('partials.article-card', ['article' => $featured, 'featured' => true])
        </section>
    @endif

    <div class="row g-5">
        <div class="col-lg-8">
            @if($search === '' && ! $activeCategory)
                <h2 class="h5 fw-bold mb-3">Latest Articles</h2>
            @endif

            @if($articles->count())
                <div class="row g-4">
                    @foreach($articles as $article)
                        <div class="col-sm-6">
                            @include('partials.article-card', ['article' => $article])
                        </div>
                    @endforeach
                </div>
                <div class="mt-4">{{ $articles->links() }}</div>
            @else
                <div class="text-center py-5 border rounded-3 bg-light">
                    <p class="text-muted mb-3">
                        @if($search !== '')
                            No articles match “{{ $search }}”. Try another keyword.
                        @elseif($activeCategory)
                            No articles in this category yet.
                        @else
                            Articles coming soon — we're preparing guides on hardware, networking and software licensing.
                        @endif
                    </p>
                    <a href="{{ route('shop.index') }}" class="btn btn-primary me-2">Browse Products</a>
                    <a href="{{ route('b2b.quote') }}" class="btn btn-outline-primary">Request a Quote</a>
                </div>
            @endif
        </div>

        <aside class="col-lg-4">
            @if($popular->isNotEmpty())
                <div class="blog-sidebar-card mb-4">
                    <h2 class="h6 fw-bold mb-3">Popular Articles</h2>
                    <ul class="list-unstyled mb-0">
                        @foreach($popular as $item)
                            <li class="d-flex gap-3 {{ ! $loop->last ? 'mb-3 pb-3 border-bottom' : '' }}">
                                <a href="{{ route('blog.show', $item) }}" class="flex-shrink-0">
                                    <img src="{{ $item->displayImageUrl() }}" alt="{{ $item->title }}" width="72" height="54" loading="lazy" class="rounded object-fit-cover" style="object-fit:cover;width:72px;height:54px;">
                                </a>
                                <div>
                                    <a href="{{ route('blog.show', $item) }}" class="small fw-semibold text-decoration-none text-dark d-block">{{ \Illuminate\Support\Str::limit($item->title, 70) }}</a>
                                    <span class="text-muted" style="font-size:.75rem;">{{ $item->published_at?->format('d M Y') }} · {{ $item->readingTimeMinutes() }} min</span>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="blog-sidebar-card mb-4">
                <h2 class="h6 fw-bold mb-3">Categories</h2>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($categories as $key => $meta)
                        <a href="{{ route('blog.category', $key) }}" class="badge rounded-pill text-bg-light border text-decoration-none px-3 py-2">{{ $meta['label'] }}</a>
                    @endforeach
                </div>
            </div>

            @include('blog.partials.newsletter')

            <div class="blog-sidebar-card blog-sidebar-card--accent">
                <h2 class="h6 fw-bold mb-2">Need a quote?</h2>
                <p class="small text-muted mb-3">VAT invoices, bulk pricing and RFQ support for businesses across South Africa.</p>
                <a href="{{ route('b2b.quote') }}" class="btn btn-primary w-100 mb-2 btn-sm">Request a Quote</a>
                <a href="{{ route('contact') }}" class="btn btn-outline-secondary w-100 btn-sm">Contact Urban Focus</a>
            </div>
        </aside>
    </div>

    <section class="blog-cta-panel mt-5">
        <div class="row align-items-center g-3">
            <div class="col-lg-8">
                <h2 class="h5 fw-bold mb-1">Need hardware for your project?</h2>
                <p class="text-muted mb-0 small">Urban Focus supplies networking, laptops, software, CCTV and enterprise IT across South Africa with VAT invoices and bulk quotes.</p>
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
