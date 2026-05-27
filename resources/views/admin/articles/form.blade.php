@extends('layouts.admin')
@section('page_title', $article->exists ? 'Edit Article' : 'New Article')
@section('content')
@include('admin.partials.blog-migration-alert')
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
                    <textarea name="content" class="form-control" rows="14">{{ old('content', $article->content) }}</textarea>
                    <div class="form-text">Use &lt;h2&gt; question headings, bullet lists and tables for featured snippets. SEO, internal links and social snippets auto-generate on save.</div>
                </div>

                @if($blogFeatures['faqs'] ?? false)
                <div class="mb-3">
                    <label class="form-label">FAQs (featured snippet + FAQ schema)</label>
                    @php $faqs = old('faqs', $article->faqs ?? [['question' => '', 'answer' => '']]); @endphp
                    @foreach($faqs as $i => $faq)
                        <div class="border rounded p-3 mb-2">
                            <input type="text" name="faqs[{{ $i }}][question]" class="form-control mb-2" placeholder="Question" value="{{ $faq['question'] ?? '' }}">
                            <textarea name="faqs[{{ $i }}][answer]" class="form-control" rows="2" placeholder="Answer">{{ $faq['answer'] ?? '' }}</textarea>
                        </div>
                    @endforeach
                    <div class="border rounded p-3 mb-0">
                        <input type="text" name="faqs[{{ count($faqs) }}][question]" class="form-control mb-2" placeholder="Add another question">
                        <textarea name="faqs[{{ count($faqs) }}][answer]" class="form-control" rows="2" placeholder="Answer"></textarea>
                    </div>
                </div>
                @endif

                @if($article->source_url)
                    <p class="small text-muted mb-0">Original source: <a href="{{ $article->source_url }}" target="_blank" rel="noopener">{{ $article->source_name ?: $article->source_url }}</a></p>
                @endif

                @if(($blogFeatures['social'] ?? false) && $article->socialSnippetList() !== [])
                    <div class="mt-4 pt-3 border-top">
                        <h2 class="h6 fw-bold">Auto-generated social snippets</h2>
                        @foreach($article->socialSnippetList() as $platform => $text)
                            <div class="mb-2">
                                <label class="form-label small text-uppercase text-muted">{{ $platform }}</label>
                                <textarea class="form-control form-control-sm" rows="2" readonly>{{ $text }}</textarea>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div></div>
        </div>
        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                @if($blogFeatures['category'] ?? false)
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <option value="">— Select —</option>
                        @foreach(config('blog.categories', []) as $key => $meta)
                            <option value="{{ $key }}" @selected(old('category', $article->category) === $key)>{{ $meta['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if($blogFeatures['authors'] ?? false)
                <div class="mb-3">
                    <label class="form-label">Author</label>
                    <select name="author_id" class="form-select">
                        <option value="">Urban Focus Team</option>
                        @foreach($authors as $author)
                            <option value="{{ $author->id }}" @selected(old('author_id', $article->author_id) == $author->id)>{{ $author->name }}</option>
                        @endforeach
                    </select>
                </div>
                @endif
                @if($blogFeatures['tags'] ?? false)
                <div class="mb-3">
                    <label class="form-label">Tags</label>
                    <input type="text" name="tags" class="form-control" value="{{ old('tags', $allTags ?? '') }}" placeholder="ups, networking, ubiquiti">
                    <div class="form-text">Comma-separated. Creates tag archive pages automatically.</div>
                </div>
                @endif
                <div class="mb-3">
                    <label class="form-label">Featured image URL</label>
                    <input type="text" name="image" class="form-control" value="{{ old('image', $article->image) }}" placeholder="https://… or storage path">
                </div>
                @if($blogFeatures['toc'] ?? false)
                <div class="form-check mb-3">
                    <input type="checkbox" name="toc_enabled" value="1" class="form-check-input" id="toc_enabled" @checked(old('toc_enabled', $article->toc_enabled ?? true))>
                    <label class="form-check-label" for="toc_enabled">Table of contents</label>
                </div>
                @endif
                @if($blogFeatures['featured'] ?? false)
                <div class="form-check mb-3">
                    <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="is_featured" @checked(old('is_featured', $article->is_featured))>
                    <label class="form-check-label" for="is_featured">Featured on blog index</label>
                </div>
                @endif
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
                <p class="small text-muted">Leave blank to auto-generate on save.</p>
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
