@props(['article', 'featured' => false])

<a href="{{ route('blog.show', $article) }}" class="article-card {{ $featured ? 'article-card--featured' : '' }} d-block h-100">
    <div class="article-card-image">
        <img src="{{ $article->displayImageUrl() }}" alt="{{ $article->title }}" loading="lazy" width="640" height="360">
    </div>
    <div class="article-card-body">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            @if($article->categoryLabel())
                <span class="article-category-badge">{{ $article->categoryLabel() }}</span>
            @endif
            <time class="small text-muted">{{ $article->published_at?->format('d M Y') }}</time>
            <span class="small text-muted">{{ $article->readingTimeMinutes() }} min read</span>
        </div>
        <h2 class="article-card-title {{ $featured ? 'h3' : 'h5' }}">{{ $article->title }}</h2>
        @if($article->excerpt)
            <p class="article-card-excerpt mb-0">{{ \Illuminate\Support\Str::limit($article->excerpt, $featured ? 220 : 140) }}</p>
        @endif
        <span class="article-card-link">Read article →</span>
    </div>
</a>
