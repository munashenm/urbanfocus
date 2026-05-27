@extends('layouts.admin')
@section('page_title', 'Content Strategy')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <p class="text-muted mb-0 small">Trending topics, Search Console metrics and top-performing blog content.</p>
    <div class="d-flex flex-wrap gap-2">
        <form action="{{ route('admin.blog-strategy.discover') }}" method="POST">@csrf
            <button type="submit" class="btn btn-outline-primary btn-sm">Discover Topics</button>
        </form>
        <form action="{{ route('admin.blog-strategy.sync-gsc') }}" method="POST">@csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Sync Search Console</button>
        </form>
        <a href="{{ route('admin.articles.index') }}" class="btn btn-primary btn-sm">Manage Articles</a>
    </div>
</div>

<div class="row g-4 mb-4">
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Published articles</div>
            <div class="h3 mb-0">{{ $metrics['published_articles'] ?? 0 }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Indexed pages (est.)</div>
            <div class="h3 mb-0">{{ $metrics['indexed_pages_estimate'] ?? 0 }}</div>
        </div></div>
    </div>
    @if(!empty($metrics['gsc']))
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">GSC clicks (28d)</div>
            <div class="h3 mb-0">{{ $metrics['gsc']['total_clicks'] ?? 0 }}</div>
        </div></div>
    </div>
    <div class="col-md-3">
        <div class="card h-100"><div class="card-body">
            <div class="text-muted small">Avg CTR</div>
            <div class="h3 mb-0">{{ $metrics['gsc']['avg_ctr'] ?? 0 }}%</div>
        </div></div>
    </div>
    @else
    <div class="col-md-6">
        <div class="alert alert-light border mb-0 h-100">
            <strong>Google Search Console</strong> — set <code>GSC_ENABLED=true</code> and upload service account JSON to enable keyword and CTR reporting.
        </div>
    </div>
    @endif
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card mb-4"><div class="card-header fw-bold">Trending topic suggestions</div>
            <div class="table-responsive"><table class="table mb-0">
                <thead><tr><th>Topic</th><th>Source</th><th>Score</th><th></th></tr></thead>
                <tbody>
                @forelse($topics as $topic)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ \Illuminate\Support\Str::limit($topic->title, 70) }}</div>
                            @if($topic->source_url)<a href="{{ $topic->source_url }}" target="_blank" class="small">Source</a>@endif
                        </td>
                        <td class="small text-muted">{{ $topic->source }}</td>
                        <td>{{ $topic->score }}</td>
                        <td class="text-end">
                            @if($topic->article_id)
                                <a href="{{ route('admin.articles.edit', $topic->article_id) }}" class="btn btn-sm btn-outline-secondary">Edit draft</a>
                            @else
                                <form action="{{ route('admin.blog-strategy.draft', $topic) }}" method="POST" class="d-inline">@csrf
                                    <select name="type" class="form-select form-select-sm d-inline-block w-auto">
                                        @foreach(config('blog_automation.article_types', []) as $key => $label)
                                            <option value="{{ $key }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-sm btn-primary">Generate draft</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted p-4">No topics yet. Click <strong>Discover Topics</strong> to pull from Reddit, RSS, NewsAPI and more.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>

        @if(!empty($metrics['top_queries']))
        <div class="card"><div class="card-header fw-bold">Top queries (Search Console)</div>
            <div class="table-responsive"><table class="table mb-0 small">
                <thead><tr><th>Page / Query</th><th>Clicks</th><th>Impr.</th><th>CTR</th></tr></thead>
                <tbody>
                @foreach(array_slice($metrics['top_queries'], 0, 12) as $row)
                    <tr>
                        <td>{{ ($row['keys'][1] ?? '') ?: ($row['keys'][0] ?? '—') }}</td>
                        <td>{{ $row['clicks'] ?? 0 }}</td>
                        <td>{{ $row['impressions'] ?? 0 }}</td>
                        <td>{{ isset($row['ctr']) ? round($row['ctr'] * 100, 1).'%' : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        @endif
    </div>

    <div class="col-lg-5">
        <div class="card mb-4"><div class="card-header fw-bold">Top blog pages (views)</div>
            <ul class="list-group list-group-flush">
                @forelse($topBlogs as $blog)
                    <li class="list-group-item d-flex justify-content-between align-items-start">
                        <div>
                            <a href="{{ route('blog.show', $blog) }}" target="_blank">{{ \Illuminate\Support\Str::limit($blog->title, 50) }}</a>
                            <div class="small text-muted">{{ $blog->views }} views</div>
                        </div>
                        <a href="{{ route('admin.articles.edit', $blog) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                    </li>
                @empty
                    <li class="list-group-item text-muted">No published articles yet.</li>
                @endforelse
            </ul>
        </div>

        @if($recentDrafts->count())
        <div class="card"><div class="card-header fw-bold">Recent drafts</div>
            <ul class="list-group list-group-flush">
                @foreach($recentDrafts as $draft)
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ \Illuminate\Support\Str::limit($draft->title, 45) }}</span>
                        <a href="{{ route('admin.articles.edit', $draft) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                    </li>
                @endforeach
            </ul>
        </div>
        @endif
    </div>
</div>
@endsection
