@extends('layouts.admin')

@section('page_title', 'Products')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <form class="d-flex flex-wrap gap-2" method="GET">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search..." value="{{ request('q') }}" style="min-width:180px">
        <select name="merchant_issue" class="form-select form-select-sm" style="min-width:180px" onchange="this.form.submit()">
            <option value="">All products</option>
            @foreach($merchantIssueLabels as $key => $label)
                <option value="{{ $key }}" @selected(request('merchant_issue') === $key)>Feed: {{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
        @if(request('merchant_issue') || request('q'))
            <a href="{{ route('admin.products.index') }}" class="btn btn-sm btn-link">Clear</a>
        @endif
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">Add Product</a>
</div>

@if(request('merchant_issue'))
    <div class="alert alert-info small py-2">Showing active products with feed issue: <strong>{{ $merchantIssueLabels[request('merchant_issue')] ?? request('merchant_issue') }}</strong></div>
@endif

<form id="bulk-products-form" method="POST" action="{{ route('admin.products.bulk-destroy') }}" onsubmit="return confirm('Delete the selected products? This cannot be undone.')">
    @csrf
    @include('partials.admin-bulk-bar', ['deleteLabel' => 'Delete selected products'])
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:2.5rem">
                        <input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="Select all products on this page">
                    </th>
                    <th>Name</th>
                    <th>SKU</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Feed</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($products as $product)
                    @php $feedIssues = $product->is_active ? $product->googleMerchantIssues() : []; @endphp
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input bulk-select" form="bulk-products-form" name="ids[]" value="{{ $product->id }}" aria-label="Select {{ $product->name }}">
                        </td>
                            <td>{{ $product->name }}</td>
                            <td>{{ $product->sku }}</td>
                            <td>R {{ number_format($product->effective_price, 2) }}</td>
                            <td>{{ $product->stock_quantity }}</td>
                            <td>
                                @if($feedIssues === [])
                                    <span class="badge bg-success">Eligible</span>
                                @else
                                    @foreach($feedIssues as $issue)
                                        <span class="badge bg-warning text-dark" title="{{ $merchantIssueLabels[$issue] ?? $issue }}">{{ $merchantIssueLabels[$issue] ?? $issue }}</span>
                                    @endforeach
                                @endif
                            </td>
                            <td>@if($product->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-muted text-center py-4">No products found.</td></tr>
                    @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $products->links() }}</div>
<p class="small text-muted mt-2">Bulk select applies to the current page only ({{ $products->count() }} of {{ $products->total() }} shown).</p>
@endsection
