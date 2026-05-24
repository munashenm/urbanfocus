@extends('layouts.admin')
@section('page_title', $article->exists ? 'Edit Article' : 'New Article')
@section('content')
<form action="{{ $article->exists ? route('admin.articles.update', $article) : route('admin.articles.store') }}" method="POST">
    @csrf
    @if($article->exists) @method('PUT') @endif
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card"><div class="card-body">
                <div class="mb-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $article->title) }}" required>
                    @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="mb-3">
                    <label class="form-label">Slug</label>
                    <input type="text" name="slug" class="form-control" value="{{ old('slug', $article->slug) }}" placeholder="auto-generated from title">
                </div>
                <div class="mb-3">
                    <label class="form-label">Excerpt</label>
                    <textarea name="excerpt" class="form-control" rows="2">{{ old('excerpt', $article->excerpt) }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Content</label>
                    <textarea name="content" class="form-control" rows="12">{{ old('content', $article->content) }}</textarea>
                    <div class="form-text">HTML allowed. Synced articles include a link to the original source.</div>
                </div>
                @if($article->source_url)
                    <p class="small text-muted mb-0">Original source: <a href="{{ $article->source_url }}" target="_blank" rel="noopener">{{ $article->source_name ?: $article->source_url }}</a></p>
                @endif
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_published" value="1" class="form-check-input" id="is_published" @checked(old('is_published', $article->is_published))>
                    <label class="form-check-label" for="is_published">Published</label>
                </div>
                <div class="mb-3">
                    <label class="form-label">Publish date</label>
                    <input type="datetime-local" name="published_at" class="form-control" value="{{ old('published_at', $article->published_at?->format('Y-m-d\TH:i')) }}">
                </div>
                <button type="submit" class="btn btn-primary w-100">Save Article</button>
            </div></div>
            <div class="card"><div class="card-body">
                <h2 class="h6 fw-bold">SEO</h2>
                <div class="mb-3">
                    <label class="form-label">Meta title</label>
                    <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $article->meta_title) }}">
                </div>
                <div class="mb-0">
                    <label class="form-label">Meta description</label>
                    <textarea name="meta_description" class="form-control" rows="3">{{ old('meta_description', $article->meta_description) }}</textarea>
                </div>
            </div></div>
        </div>
    </div>
</form>
@endsection
