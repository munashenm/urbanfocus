@extends('layouts.admin')
@section('page_title', 'Blog Articles')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <form action="{{ route('admin.articles.sync') }}" method="POST" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-outline-primary btn-sm">Sync IT News (RSS)</button>
    </form>
    <a href="{{ route('admin.articles.create') }}" class="btn btn-primary btn-sm">Write Article</a>
</div>

<div class="alert alert-light border small mb-4">
    <strong>News sources:</strong> MyBroadband, ITWeb, TechCentral (free RSS).
    Optional: set <code>NEWSAPI_KEY</code> in .env for global tech headlines via
    <a href="https://newsapi.org/register" target="_blank" rel="noopener">NewsAPI.org</a>.
    Synced articles import as <strong>drafts</strong> — review and publish from here.
</div>

<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Title</th><th>Source</th><th>Status</th><th>Date</th><th></th></tr></thead>
<tbody>
@forelse($articles as $article)
<tr>
    <td>{{ $article->title }}</td>
    <td class="small text-muted">{{ $article->source_name ?: 'Manual' }}</td>
    <td>
        @if($article->is_published)<span class="badge bg-success">Published</span>
        @else<span class="badge bg-secondary">Draft</span>@endif
    </td>
    <td class="small">{{ $article->published_at?->format('d M Y') ?: $article->created_at->format('d M Y') }}</td>
    <td class="text-end">
        @if($article->is_published)<a href="{{ route('blog.show', $article) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>@endif
        <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        <form action="{{ route('admin.articles.destroy', $article) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this article?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
    </td>
</tr>
@empty
<tr><td colspan="5" class="text-muted p-4">No articles yet. Sync news or write one manually.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $articles->links() }}</div>
@endsection
