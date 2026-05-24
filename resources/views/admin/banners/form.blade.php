@extends('layouts.admin')
@section('page_title', $banner->exists ? 'Edit Banner' : 'Add Banner')
@section('content')
<form action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" method="POST" class="card"><div class="card-body">
@csrf @if($banner->exists) @method('PUT') @endif
<div class="mb-3"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" value="{{ old('title', $banner->title) }}" required></div>
<div class="mb-3"><label class="form-label">Subtitle</label><input type="text" name="subtitle" class="form-control" value="{{ old('subtitle', $banner->subtitle) }}"></div>
<div class="mb-3"><label class="form-label">Link URL</label><input type="text" name="link" class="form-control" value="{{ old('link', $banner->link) }}" placeholder="/shop"></div>
<div class="mb-3"><label class="form-label">Button Text</label><input type="text" name="button_text" class="form-control" value="{{ old('button_text', $banner->button_text) }}"></div>
<div class="mb-3"><label class="form-label">Placement</label><select name="placement" class="form-select"><option value="home" @selected(old('placement',$banner->placement)==='home')>Homepage</option><option value="shop" @selected(old('placement',$banner->placement)==='shop')>Shop</option></select></div>
<div class="mb-3"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="{{ old('sort_order', $banner->sort_order ?? 0) }}"></div>
<div class="form-check mb-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $banner->is_active ?? true))><label class="form-check-label">Active</label></div>
<button type="submit" class="btn btn-primary">Save Banner</button>
</div></form>
@endsection
