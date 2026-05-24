@extends('layouts.app')

@section('title', 'IT Insights & Guides | Urban Focus Blog')

@section('content')
<div class="container py-4">
    <h1 class="h2 fw-bold mb-2">IT Insights &amp; Guides</h1>
    <p class="text-muted mb-4">Buying guides, product news and industry updates from Urban Focus.</p>

    @if($articles->count())
        <div class="row g-4">
            @foreach($articles as $article)
                <div class="col-md-4">
                    <a href="{{ route('blog.show', $article) }}" class="article-card d-block h-100 p-4">
                        <time class="small text-muted">{{ $article->published_at?->format('d M Y') }}</time>
                        <h2 class="h5 fw-bold mt-1">{{ $article->title }}</h2>
                        <p class="small text-muted mb-0">{{ \Illuminate\Support\Str::limit($article->excerpt, 140) }}</p>
                    </a>
                </div>
            @endforeach
        </div>
        <div class="mt-4">{{ $articles->links() }}</div>
    @else
        <p class="text-muted">Articles coming soon.</p>
    @endif
</div>
@endsection
