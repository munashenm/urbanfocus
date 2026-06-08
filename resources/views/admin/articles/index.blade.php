@extends('layouts.admin')
@section('page_title', 'Blog Articles')
@section('content')
@include('admin.partials.blog-migration-alert')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div class="d-flex flex-wrap gap-2">
        <form action="{{ route('admin.articles.sync') }}" method="POST" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm">Sync IT News (RSS)</button>
        </form>
        <form action="{{ route('admin.articles.seed-pillars') }}" method="POST" class="d-inline" onsubmit="return confirm('Seed or update SEO pillar articles? Existing slugs will be updated.')">
            @csrf
            <button type="submit" class="btn btn-outline-secondary btn-sm">Seed Pillar Articles</button>
        </form>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('admin.blog-strategy.index') }}" class="btn btn-outline-primary btn-sm">Content Strategy</a>
        <a href="{{ route('admin.articles.create') }}" class="btn btn-primary btn-sm">Write Article</a>
    </div>
</div>

<div class="alert alert-light border small mb-4">
    <strong>News sources:</strong> MyBroadband, ITWeb, TechCentral (free RSS).
    Optional: set <code>NEWSAPI_KEY</code> in .env for global tech headlines via
    <a href="https://newsapi.org/register" target="_blank" rel="noopener">NewsAPI.org</a>.
    Synced articles import as <strong>{{ config('news.publish_as_draft', true) ? 'drafts' : 'published' }}</strong> under the <em>Industry News</em> category.
    Use <strong>Seed Pillar Articles</strong> to add original SEO buying guides (run migrations first if upgrading).
</div>

<form id="bulk-articles-form" method="POST">
    @csrf
    <div class="bulk-action-bar d-none align-items-center gap-2 mb-3 px-3 py-2 bg-light border rounded" id="bulk-action-bar">
        <span class="small mb-0 me-1"><strong id="bulk-selected-count">0</strong> selected</span>
        <button type="submit" formaction="{{ route('admin.articles.bulk-publish') }}" class="btn btn-sm btn-success">Publish selected</button>
        <button type="submit" formaction="{{ route('admin.articles.bulk-unpublish') }}" class="btn btn-sm btn-outline-secondary">Move to draft</button>
        <button type="submit" formaction="{{ route('admin.articles.bulk-destroy') }}" class="btn btn-sm btn-danger" onclick="return confirm('Delete the selected articles? This cannot be undone.')">Delete selected</button>
    </div>
</form>

<div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
<thead><tr>
    <th style="width:2.5rem"><input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="Select all articles on this page"></th>
    <th>Title</th><th>Category</th><th>Source</th><th>Status</th><th>Date</th><th></th>
</tr></thead>
<tbody>
@forelse($articles as $article)
<tr>
    <td><input type="checkbox" class="form-check-input bulk-select" form="bulk-articles-form" name="ids[]" value="{{ $article->id }}" aria-label="Select {{ $article->title }}"></td>
    <td>
        {{ $article->title }}
        @if($article->is_featured)<span class="badge bg-info ms-1">Featured</span>@endif
    </td>
    <td class="small">{{ $article->categoryLabel() ?: '—' }}</td>
    <td class="small text-muted">{{ $article->source_name ?: 'Manual' }}</td>
    <td>
        @if($article->is_published)<span class="badge bg-success">Published</span>
        @else<span class="badge bg-secondary">Draft</span>@endif
    </td>
    <td class="small">{{ $article->published_at?->format('d M Y') ?: $article->created_at->format('d M Y') }}</td>
    <td class="text-end text-nowrap">
        <form action="{{ route('admin.articles.toggle-publish', $article) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-sm {{ $article->is_published ? 'btn-outline-secondary' : 'btn-outline-success' }}">{{ $article->is_published ? 'Unpublish' : 'Publish' }}</button>
        </form>
        @if($article->is_published)<a href="{{ route('blog.show', $article) }}" target="_blank" class="btn btn-sm btn-outline-secondary">View</a>@endif
        <a href="{{ route('admin.articles.edit', $article) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        <form action="{{ route('admin.articles.destroy', $article) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this article?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
    </td>
</tr>
@empty
<tr><td colspan="7" class="text-muted p-4">No articles yet. Sync news or write one manually.</td></tr>
@endforelse
</tbody></table></div></div>
<div class="mt-3">{{ $articles->links() }}</div>

<script>
(function () {
    const bar = document.getElementById('bulk-action-bar');
    const countEl = document.getElementById('bulk-selected-count');
    const selectAll = document.getElementById('bulk-select-all');
    const boxes = () => Array.from(document.querySelectorAll('.bulk-select'));

    function updateBar() {
        const checked = boxes().filter((cb) => cb.checked);
        if (countEl) countEl.textContent = String(checked.length);
        if (bar) {
            bar.classList.toggle('d-none', checked.length === 0);
            bar.classList.toggle('d-flex', checked.length > 0);
        }
        if (selectAll) {
            const all = boxes();
            selectAll.checked = all.length > 0 && checked.length === all.length;
            selectAll.indeterminate = checked.length > 0 && checked.length < all.length;
        }
    }

    selectAll?.addEventListener('change', () => {
        boxes().forEach((cb) => { cb.checked = selectAll.checked; });
        updateBar();
    });
    boxes().forEach((cb) => cb.addEventListener('change', updateBar));
    updateBar();
})();
</script>
@endsection
