@extends('layouts.admin')

@section('page_title', $category->exists ? 'Edit Category' : 'Add Category')

@section('content')
<form action="{{ $category->exists ? route('admin.categories.update', $category) : route('admin.categories.store') }}" method="POST">
    @csrf
    @if($category->exists) @method('PUT') @endif
    <div class="card"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-control" value="{{ old('name', $category->name) }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input type="text" name="slug" class="form-control" value="{{ old('slug', $category->slug) }}">
                <div class="form-text">Leave blank to auto-generate. Subcategory URLs use parent/child slugs.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Parent category</label>
                <select name="parent_id" class="form-select">
                    <option value="">None — this is a main category</option>
                    @foreach($parents as $parent)
                        <option value="{{ $parent->id }}" @selected(old('parent_id', $category->parent_id) == $parent->id)>{{ $parent->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Sort order</label>
                <input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $category->sort_order ?? 0) }}">
            </div>
            <div class="col-md-3">
                <label class="form-label">Status</label>
                <div class="form-check mt-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1" class="form-check-input" id="cat-active" @checked(old('is_active', $category->is_active ?? true))>
                    <label class="form-check-label" for="cat-active">Enabled</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="3">{{ old('description', $category->description) }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Category image URL</label>
                <input type="text" name="image" class="form-control" value="{{ old('image', $category->image) }}" placeholder="/images/categories/example.jpg or https://…">
            </div>
            <div class="col-12">
                <label class="form-label">SEO title</label>
                <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $category->meta_title) }}">
            </div>
            <div class="col-12">
                <label class="form-label">SEO meta description</label>
                <textarea name="meta_description" class="form-control" rows="2">{{ old('meta_description', $category->meta_description) }}</textarea>
            </div>
            <div class="col-12">
                <label class="form-label">SEO keywords</label>
                <input type="text" name="meta_keywords" class="form-control" value="{{ old('meta_keywords', $category->meta_keywords) }}">
            </div>
            @if($category->exists)
                <div class="col-12">
                    <div class="alert alert-light border small mb-0">
                        Public URL: <a href="{{ $category->url() }}" target="_blank">{{ $category->url() }}</a>
                        @if($category->parent)<br>Path: {{ $category->fullPathLabel() }}@endif
                    </div>
                </div>
            @endif
            <div class="col-12">
                <button type="submit" class="btn btn-primary">Save category</button>
                <a href="{{ route('admin.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div></div>
</form>
@endsection
