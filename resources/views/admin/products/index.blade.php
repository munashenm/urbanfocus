@extends('layouts.admin')

@section('page_title', 'Products')

@section('content')
<div class="admin-page-actions">
    <form class="admin-filters" method="GET">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search name, SKU, brand…" value="{{ request('q') }}">
        <select name="status" class="form-select form-select-sm">
            <option value="">All statuses</option>
            @foreach(\App\Models\Product::publicationStatuses() as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="category_id" class="form-select form-select-sm">
            <option value="">All categories</option>
            @foreach($categories as $category)
                <option value="{{ $category->id }}" @selected((string) request('category_id') === (string) $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        <select name="brand" class="form-select form-select-sm">
            <option value="">All brands</option>
            @foreach($brands as $brandName)
                <option value="{{ $brandName }}" @selected(request('brand') === $brandName)>{{ $brandName }}</option>
            @endforeach
        </select>
        <select name="merchant_issue" class="form-select form-select-sm">
            <option value="">Feed eligibility</option>
            @foreach($merchantIssueLabels as $key => $label)
                <option value="{{ $key }}" @selected(request('merchant_issue') === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
        @if(request()->hasAny(['q', 'status', 'category_id', 'brand', 'merchant_issue']))
            <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-link">Clear</a>
        @endif
    </form>
    <div class="d-flex flex-wrap gap-2">
        @permission('products.create')
            <a href="{{ route('admin.catalog.index') }}" class="btn btn-outline-secondary btn-sm">Import CSV</a>
        @endpermission
        <a href="{{ route('admin.products.export', request()->only(['status', 'q'])) }}" class="btn btn-outline-secondary btn-sm">Export CSV</a>
        @permission('products.create')
            <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">Add product</a>
        @endpermission
    </div>
</div>

<form id="bulk-products-form" method="POST" action="{{ route('admin.products.bulk-destroy') }}">
    @csrf
    <div class="card admin-card admin-data-table mb-3">
        <div class="card-header bg-white d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="Select all products on this page">
                <label for="bulk-select-all" class="small mb-0">Select all on page</label>
            </div>
            @anypermission('products.edit', 'products.delete')
                <div class="d-flex flex-wrap gap-2">
                    @permission('products.edit')
                        <button type="submit" formaction="{{ route('admin.products.bulk-update') }}" name="action" value="publish" class="btn btn-sm btn-outline-success" form="bulk-products-form" onclick="return confirm('Publish selected products?')">Publish</button>
                        <button type="submit" formaction="{{ route('admin.products.bulk-update') }}" name="action" value="draft" class="btn btn-sm btn-outline-secondary" form="bulk-products-form">Move to draft</button>
                        <button type="submit" formaction="{{ route('admin.products.bulk-update') }}" name="action" value="archive" class="btn btn-sm btn-outline-warning" form="bulk-products-form" onclick="return confirm('Archive selected products?')">Archive</button>
                    @endpermission
                    @permission('products.delete')
                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete selected products?')">Delete</button>
                    @endpermission
                </div>
            @endanypermission
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th style="width:2.5rem"></th>
                        <th style="width:56px"></th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Feed</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $product)
                        @php $feedIssues = $product->is_active && ! $product->trashed() ? $product->googleMerchantIssues() : []; @endphp
                        <tr>
                            <td><input type="checkbox" class="form-check-input bulk-select" form="bulk-products-form" name="ids[]" value="{{ $product->id }}"></td>
                            <td>
                                @if($product->primary_image_url)
                                    <img src="{{ $product->primary_image_url }}" alt="" class="admin-product-thumb" width="48" height="48">
                                @else
                                    <span class="admin-product-thumb admin-product-thumb--empty">📦</span>
                                @endif
                            </td>
                            <td>
                                <a href="{{ route('admin.products.edit', $product) }}" class="fw-semibold text-decoration-none">{{ $product->name }}</a>
                                <div class="small text-muted">{{ $product->category?->name ?: 'Uncategorised' }}@if($product->brand) · {{ $product->brand }}@endif</div>
                            </td>
                            <td><code>{{ $product->sku ?: '—' }}</code></td>
                            <td>
                                R {{ number_format($product->effective_price, 2) }}
                                @if($product->is_on_sale)<br><small class="text-muted"><s>R {{ number_format($product->price, 2) }}</s></small>@endif
                            </td>
                            <td>{{ $product->manage_stock ? $product->stock_quantity : ($product->in_stock ? 'In stock' : 'Out') }}</td>
                            <td>
                                @php $pub = $product->publicationStatus(); @endphp
                                <span class="badge @if($pub === 'published') bg-success @elseif($pub === 'draft') bg-secondary @else bg-warning text-dark @endif">{{ $product->publicationStatusLabel() }}</span>
                            </td>
                            <td>
                                @if($feedIssues === [])
                                    <span class="badge bg-success">OK</span>
                                @else
                                    <span class="badge bg-warning text-dark" title="{{ collect($feedIssues)->map(fn ($i) => $merchantIssueLabels[$i] ?? $i)->implode(', ') }}">{{ count($feedIssues) }} issue(s)</span>
                                @endif
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                @permission('products.create')
                                    <form action="{{ route('admin.products.duplicate', $product) }}" method="POST" class="d-inline">
                                        @csrf
                                        <button class="btn btn-sm btn-outline-secondary">Duplicate</button>
                                    </form>
                                @endpermission
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="admin-empty">No products found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</form>

<div>{{ $products->links() }}</div>
<p class="small text-muted mt-2">Showing {{ $products->count() }} of {{ $products->total() }} products.</p>

@push('scripts')
<script>
document.getElementById('bulk-select-all')?.addEventListener('change', function () {
    document.querySelectorAll('.bulk-select').forEach(cb => cb.checked = this.checked);
});
</script>
@endpush
@endsection
