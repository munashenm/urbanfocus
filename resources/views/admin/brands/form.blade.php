@extends('layouts.admin')
@section('page_title', $brand->exists ? 'Edit Brand' : 'Add Brand')
@section('content')
<form action="{{ $brand->exists ? route('admin.brands.update', $brand) : route('admin.brands.store') }}" method="POST" enctype="multipart/form-data" class="card"><div class="card-body">
@csrf @if($brand->exists) @method('PUT') @endif
<div class="mb-3"><label class="form-label">Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $brand->name) }}" required></div>
<div class="mb-3"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="{{ old('slug', $brand->slug) }}"></div>
<div class="mb-3">
    <label class="form-label">Logo</label>
    @if($brand->logo)
        <div class="mb-2 p-2 border rounded d-inline-block bg-white">
            <img src="{{ asset($brand->logo) }}" alt="" height="40">
        </div>
    @endif
    <input type="file" name="logo_file" class="form-control" accept="image/*,.svg">
    <div class="form-text">PNG or SVG recommended. Official partner logos from brand portals look best.</div>
</div>
<div class="mb-3"><label class="form-label">Website</label><input type="url" name="website" class="form-control" value="{{ old('website', $brand->website) }}"></div>
<div class="mb-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $brand->sort_order ?? 0) }}"></div>
<div class="form-check mb-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $brand->is_active ?? true))><label class="form-check-label">Active</label></div>
<button type="submit" class="btn btn-primary">Save Brand</button>
</div></form>
@endsection
