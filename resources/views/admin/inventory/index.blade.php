@extends('layouts.admin')

@section('page_title', 'Inventory')

@section('content')
<div class="row g-3 mb-4">
    @foreach($stats as $label => $value)
        <div class="col-6 col-md-3"><div class="stat-card"><div class="text-muted small">{{ ucfirst(str_replace('_', ' ', $label)) }}</div><div class="value">{{ $value }}</div></div></div>
    @endforeach
</div>

<div class="admin-page-actions">
    <form class="admin-filters" method="GET">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search SKU or name…" value="{{ request('q') }}">
        <select name="filter" class="form-select form-select-sm">
            <option value="">All stock</option>
            <option value="in_stock" @selected(request('filter') === 'in_stock')>In stock</option>
            <option value="low_stock" @selected(request('filter') === 'low_stock')>Low stock (≤5)</option>
            <option value="out_of_stock" @selected(request('filter') === 'out_of_stock')>Out of stock</option>
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
</div>

<div class="card admin-card admin-data-table">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Product</th><th>SKU</th><th>Category</th><th>Stock</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->sku }}</td>
                        <td>{{ $product->category?->name ?? '—' }}</td>
                        <td>{{ $product->manage_stock ? $product->stock_quantity : '—' }}</td>
                        <td>
                            @if($product->in_stock)
                                <span class="badge bg-success">In stock</span>
                            @else
                                <span class="badge bg-danger">Out of stock</span>
                            @endif
                        </td>
                        <td class="text-end"><a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="admin-empty">No products found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
