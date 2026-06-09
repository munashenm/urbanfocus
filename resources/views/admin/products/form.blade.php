@extends('layouts.admin')

@section('page_title', $product->exists ? 'Edit Product' : 'Add Product')

@section('content')
<form action="{{ $product->exists ? route('admin.products.update', $product) : route('admin.products.store') }}" method="POST" enctype="multipart/form-data" class="wc-product-form">
    @csrf
    @if($product->exists) @method('PUT') @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card admin-card mb-4">
                <div class="card-body">
                    <label class="form-label fw-semibold">Product name *</label>
                    <input type="text" name="name" class="form-control form-control-lg mb-3" value="{{ old('name', $product->name) }}" required placeholder="Product name">

                    <label class="form-label">Permalink</label>
                    <div class="input-group mb-3">
                        <span class="input-group-text small">{{ url('/product') }}/</span>
                        <input type="text" name="slug" class="form-control" value="{{ old('slug', $product->slug) }}" placeholder="auto-generated-from-name">
                    </div>

                    <label class="form-label">Short description</label>
                    <textarea name="short_description" class="form-control mb-3" rows="2" placeholder="Brief summary shown in listings">{{ old('short_description', $product->short_description) }}</textarea>
                </div>
            </div>

            <div class="card admin-card mb-4">
                <div class="card-header bg-white p-0">
                    <ul class="nav nav-tabs wc-product-tabs" role="tablist">
                        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-description" type="button">Description</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-pricing" type="button">Pricing</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-inventory" type="button">Inventory</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-shipping" type="button">Shipping</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-attributes" type="button">Attributes</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-seo" type="button">SEO</button></li>
                        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-images" type="button">Images</button></li>
                    </ul>
                </div>
                <div class="card-body tab-content">
                    <div class="tab-pane fade show active" id="tab-description">
                        <label class="form-label">Full description</label>
                        <textarea name="description" class="form-control" rows="10" placeholder="Detailed product description (HTML allowed)">{{ old('description', $product->description) }}</textarea>
                    </div>

                    <div class="tab-pane fade" id="tab-pricing">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Regular price (ZAR) *</label>
                                <input type="number" step="0.01" name="price" class="form-control" value="{{ old('price', $product->price) }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Sale price</label>
                                <input type="number" step="0.01" name="sale_price" class="form-control" value="{{ old('sale_price', $product->sale_price) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cost price</label>
                                <input type="number" step="0.01" name="cost_price" class="form-control" value="{{ old('cost_price', $product->cost_price) }}">
                            </div>
                        </div>
                        <div class="alert alert-light border mt-3 mb-0 small">
                            @if($pricesIncludeVat)
                                Prices are stored <strong>including {{ $vatRate }}% VAT</strong>. Storefront displays VAT-inclusive pricing.
                            @else
                                Prices are stored <strong>excluding VAT</strong>. {{ $vatRate }}% VAT is added at checkout.
                            @endif
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-inventory">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">SKU</label>
                                <input type="text" name="sku" class="form-control" value="{{ old('sku', $product->sku) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Barcode / GTIN</label>
                                <input type="text" name="barcode" class="form-control" value="{{ old('barcode', $product->barcode) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Stock quantity *</label>
                                <input type="number" name="stock_quantity" class="form-control" value="{{ old('stock_quantity', $product->stock_quantity ?? 0) }}" required>
                            </div>
                            <div class="col-md-6 d-flex align-items-end">
                                <div>
                                    <div class="form-check mb-2">
                                        <input type="hidden" name="manage_stock" value="0">
                                        <input type="checkbox" name="manage_stock" value="1" class="form-check-input" id="manage_stock" @checked(old('manage_stock', $product->manage_stock ?? true))>
                                        <label class="form-check-label" for="manage_stock">Track stock quantity</label>
                                    </div>
                                    <div class="form-check">
                                        <input type="hidden" name="in_stock" value="0">
                                        <input type="checkbox" name="in_stock" value="1" class="form-check-input" id="in_stock" @checked(old('in_stock', $product->in_stock ?? true))>
                                        <label class="form-check-label" for="in_stock">In stock (when not tracking quantity)</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-shipping">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Weight (kg)</label>
                                <input type="number" step="0.01" name="weight" class="form-control" value="{{ old('weight', $product->weight) }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Dimensions</label>
                                <input type="text" name="dimensions" class="form-control" value="{{ old('dimensions', $product->dimensions) }}" placeholder="W x H x D cm">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Delivery ETA (days)</label>
                                <input type="number" name="delivery_days" class="form-control" value="{{ old('delivery_days', $product->delivery_days) }}">
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-attributes">
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Model number</label>
                                <input type="text" name="model_number" class="form-control" value="{{ old('model_number', $product->model_number) }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Warranty (months)</label>
                                <input type="number" name="warranty_months" class="form-control" value="{{ old('warranty_months', $product->warranty_months) }}">
                            </div>
                        </div>
                        <label class="form-label">Specifications</label>
                        <textarea name="specifications" class="form-control" rows="6" placeholder="CPU: Intel i7&#10;RAM: 16GB&#10;Storage: 512GB SSD">{{ old('specifications', $product->specifications ? collect($product->specifications)->map(fn($v,$k)=>$k.': '.$v)->implode("\n") : '') }}</textarea>
                        <div class="form-text">One spec per line using <code>Label: Value</code></div>
                    </div>

                    <div class="tab-pane fade" id="tab-seo">
                        <div class="mb-3">
                            <label class="form-label">Google product category</label>
                            <input type="text" name="google_product_category" class="form-control" value="{{ old('google_product_category', $product->google_product_category) }}" placeholder="Electronics &gt; Computers">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SEO title</label>
                            <input type="text" name="meta_title" class="form-control" value="{{ old('meta_title', $product->meta_title) }}">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">SEO description</label>
                            <textarea name="meta_description" class="form-control" rows="2">{{ old('meta_description', $product->meta_description) }}</textarea>
                        </div>
                        <div class="mb-0">
                            <label class="form-label">Meta keywords</label>
                            <input type="text" name="meta_keywords" class="form-control" value="{{ old('meta_keywords', $product->meta_keywords) }}">
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-images">
                        @if($product->exists && $product->images->count())
                            <div class="wc-image-gallery mb-4">
                                @foreach($product->images as $image)
                                    <div class="wc-image-item">
                                        <img src="{{ $image->url }}" alt="">
                                        @if($image->is_primary)<span class="badge bg-primary wc-image-badge">Primary</span>@endif
                                        <div class="wc-image-actions">
                                            @unless($image->is_primary)
                                                <form action="{{ route('admin.products.images.primary', [$product, $image]) }}" method="POST" class="d-inline">
                                                    @csrf @method('PATCH')
                                                    <button class="btn btn-sm btn-light">Primary</button>
                                                </form>
                                            @endunless
                                            <form action="{{ route('admin.products.images.destroy', [$product, $image]) }}" method="POST" class="d-inline" onsubmit="return confirm('Remove this image?')">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">Remove</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="mb-3">
                            <label class="form-label">Upload images</label>
                            <input type="file" name="images[]" class="form-control" multiple accept="image/*">
                        </div>
                        <div>
                            <label class="form-label">Import from image URLs</label>
                            <textarea name="image_urls" class="form-control" rows="3" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg">{{ old('image_urls') }}</textarea>
                            <div class="form-text">One URL per line. Images are downloaded when you save.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            @if($product->exists)
                @php $feedIssues = $product->publicationStatus() === 'published' ? $product->googleMerchantIssues() : ['inactive']; @endphp
                <div class="card admin-card mb-4 border-{{ $feedIssues === [] ? 'success' : 'warning' }}">
                    <div class="card-header bg-white fw-semibold">Google Merchant</div>
                    <div class="card-body">
                        @if($feedIssues === [])
                            <span class="badge bg-success">Eligible for feed</span>
                        @else
                            @foreach($feedIssues as $issue)
                                <span class="badge bg-warning text-dark me-1">{{ $issue === 'inactive' ? 'Not published' : (\App\Models\Product::googleMerchantIssueLabels()[$issue] ?? $issue) }}</span>
                            @endforeach
                        @endif
                    </div>
                </div>
            @endif

            <div class="card admin-card mb-4">
                <div class="card-header bg-white fw-semibold">Publish</div>
                <div class="card-body">
                    <label class="form-label">Status</label>
                    <select name="publication_status" class="form-select mb-3">
                        @foreach($publicationStatuses as $value => $label)
                            <option value="{{ $value }}" @selected(old('publication_status', $product->exists ? $product->publicationStatus() : 'draft') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <div class="form-check mb-2">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" class="form-check-input" id="is_featured" @checked(old('is_featured', $product->is_featured))>
                        <label class="form-check-label" for="is_featured">Featured product</label>
                    </div>
                    <div class="form-check mb-2">
                        <input type="hidden" name="is_deal" value="0">
                        <input type="checkbox" name="is_deal" value="1" class="form-check-input" id="is_deal" @checked(old('is_deal', $product->is_deal))>
                        <label class="form-check-label" for="is_deal">Deal product</label>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Deal label</label>
                        <input type="text" name="deal_label" class="form-control" value="{{ old('deal_label', $product->deal_label) }}" placeholder="Hot Deal">
                    </div>
                </div>
                <div class="card-footer bg-white d-grid gap-2">
                    <button type="submit" class="btn btn-primary">{{ $product->exists ? 'Update product' : 'Create product' }}</button>
                    @if($product->exists)
                        @permission('products.create')
                            <button type="submit" formaction="{{ route('admin.products.duplicate', $product) }}" formnovalidate class="btn btn-outline-secondary" onclick="event.preventDefault(); document.getElementById('duplicate-form').submit();">Duplicate</button>
                        @endpermission
                        @if($product->publicationStatus() === 'published')
                            <a href="{{ route('products.show', $product) }}" class="btn btn-outline-secondary" target="_blank">View on store</a>
                        @endif
                    @endif
                </div>
            </div>

            <div class="card admin-card mb-4">
                <div class="card-header bg-white fw-semibold">Product organisation</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select">
                            <option value="">Uncategorised</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" @selected(old('category_id', $product->category_id) == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Brand</label>
                        <input type="text" name="brand" class="form-control" list="brand-options" value="{{ old('brand', $product->brand) }}" placeholder="e.g. Dell, HP">
                        <datalist id="brand-options">
                            @foreach($brands as $brandName)
                                <option value="{{ $brandName }}">
                            @endforeach
                        </datalist>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

@if($product->exists)
    @permission('products.create')
        <form id="duplicate-form" action="{{ route('admin.products.duplicate', $product) }}" method="POST" class="d-none">@csrf</form>
    @endpermission
@endif
@endsection
