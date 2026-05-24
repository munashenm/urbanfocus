@extends('layouts.app')

@section('title', $article->seoTitle())
@section('meta_description', $article->seoDescription())
@section('canonical', route('blog.show', $article))

@section('content')
<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Blog</a></li>
            <li class="breadcrumb-item active">{{ $article->title }}</li>
        </ol>
    </nav>

    <article class="row justify-content-center">
        <div class="col-lg-8">
            <header class="mb-4">
                <time class="text-muted small">{{ $article->published_at?->format('d F Y') }}</time>
                <h1 class="h2 fw-bold mt-1">{{ $article->title }}</h1>
                @if($article->excerpt)<p class="lead text-muted">{{ $article->excerpt }}</p>@endif
            </header>
            <div class="article-content">
                {!! nl2br(e($article->content)) !!}
            </div>
        </div>
    </article>

    @if($related->count())
        <section class="mt-5 pt-4 border-top">
            <h2 class="h5 fw-bold mb-3">Related Articles</h2>
            <div class="row g-3">
                @foreach($related as $item)
                    <div class="col-md-4">
                        <a href="{{ route('blog.show', $item) }}" class="article-card d-block p-3">{{ $item->title }}</a>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
</div>
@endsection

@push('schema')
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "Article",
    "headline": {{ json_encode($article->title) }},
    "datePublished": "{{ $article->published_at?->toAtomString() }}",
    "author": { "@type": "Organization", "name": "Urban Focus" },
    "publisher": { "@type": "Organization", "name": "Urban Focus", "logo": { "@type": "ImageObject", "url": "{{ asset('images/logo-stacked.png') }}" } }
}
</script>
@endpush
