@extends('layouts.admin')

@section('page_title', $product->exists ? 'Edit Product' : 'Add Product')

@section('content')
<form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    @if($product->exists) @method('PUT') @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4"><div class="card-body">
                <div class="mb-3"><label class="form-label">Product Name *</label><input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required></div>
                <div class="mb-3"><label class="form-label">Slug</label><input type="text" name="slug" class="form-control" value="{{ old('slug', $product->slug) }}" placeholder="auto-generated"></div>
                <div class="mb-3"><label class="form-label">Short Description</label><textarea name="short_description" class="form-control" rows="2">{{ old('short_description', $product->short_description) }}</textarea></div>
                <div class="mb-3"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="6">{{ old('description', $product->description) }}</textarea></div>
            </div></div>

            <div class="card mb-4"><div class="card-body">
                <h3 class="h6 fw-bold">SEO</h3>
                <div class="mb-3"><label class="form-label">Meta Title</label><input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $product->meta_title) }}"></div>
                <div class="mb-3"><label class="form-label">Meta Description</label><textarea name="meta_description" class="form-control" rows="2">{{ old('meta_description', $product->meta_description) }}</textarea></div>
                <div class="mb-3"><label class="form-label">Meta Keywords</label><input type="text" name="meta_keywords" class="form-control" value="{{ old('meta_keywords', $product->meta_keywords) }}"></div>
            </div></div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-4"><div class="card-body">
                <div class="mb-3"><label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">None</option>
                        @foreach($categories as $cat)
                            <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">SKU</label><input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}"></div>
                <div class="mb-3"><label class="form-label">Brand</label><input type="text" name="brand" class="form-control" value="{{ old('brand', $product->brand) }}"></div>
                <div class="mb-3"><label class="form-label">Price (ZAR) *</label><input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $product->price) }}" required></div>
                <div class="mb-3"><label class="form-label">Sale Price</label><input type="number" step="0.01" name="sale_price" class="form-control" value="{{ old('sale_price', $product->sale_price) }}"></div>
                <div class="mb-3"><label class="form-label">Stock Quantity *</label><input type="number" name="stock_quantity" class="form-control" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required></div>
                <div class="form-check mb-2"><input type="hidden" name="manage_stock" value="0"><input type="checkbox" name="manage_stock" value="1" class="form-check-input" @checked(old('manage_stock', $product->manage_stock ?? true))><label class="form-check-label">Manage stock</label></div>
                <div class="form-check mb-2"><input type="hidden" name="in_stock" value="0"><input type="checkbox" name="in_stock" value="1" class="form-check-input" @checked(old('in_stock', $product->in_stock ?? true))><label class="form-check-label">In stock</label></div>
                <div class="form-check mb-2"><input type="hidden" name="is_featured" value="0"><input type="checkbox" name="is_featured" value="1" class="form-check-input" @checked(old('is_featured', $product->is_featured))><label class="form-check-label">Featured</label></div>
                <div class="form-check mb-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $product->is_active ?? true))><label class="form-check-label">Active</label></div>
                <div class="mb-3"><label class="form-label">Upload Images</label><input type="file" name="images[]" class="form-control" multiple accept="image/*"></div>
                @if($product->exists && $product->images->count())
                    <div class="d-flex flex-wrap gap-2">
                        @foreach($product->images as $img)
                            <img src="{{ asset('storage/'.$img->path) }}" alt="" style="width:60px;height:60px;object-fit:cover;border-radius:4px">
                        @endforeach
                    </div>
                @endif
            </div></div>
            <button type="submit" class="btn btn-primary w-100">Save Product</button>
        </div>
    </div>
</form>
@endsection
