@extends('layouts.app')

@section('title', 'IT Insights & Guides | Urban Focus Blog')
@section('meta_description', 'Buying guides, product news and IT industry updates from Urban Focus — your South African IT hardware and software partner.')

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
        <div class="text-center py-5">
            <p class="text-muted mb-3">Articles coming soon — we're preparing guides on hardware, networking and software licensing.</p>
            <a href="{{ route('shop.index') }}" class="btn btn-primary me-2">Browse Products</a>
            <a href="{{ route('contact') }}" class="btn btn-outline-primary">Contact Us</a>
        </div>
    @endif
</div>
@endsection
