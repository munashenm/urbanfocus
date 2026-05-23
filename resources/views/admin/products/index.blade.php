@extends('layouts.admin')

@section('page_title', 'Products')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <form class="d-flex" method="GET">
        <input type="search" name="q" class="form-control form-control-sm me-2" placeholder="Search..." value="{{ request('q') }}">
        <button class="btn btn-sm btn-outline-secondary">Search</button>
    </form>
    <a href="{{ route('admin.products.create') }}" class="btn btn-primary btn-sm">Add Product</a>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Name</th><th>SKU</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->sku }}</td>
                        <td>R {{ number_format($product->effective_price, 2) }}</td>
                        <td>{{ $product->stock_quantity }}</td>
                        <td>@if($product->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
                        <td class="text-end">
                            <a href="{{ route('admin.products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            <form action="{{ route('admin.products.destroy', $product) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this product?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $products->links() }}</div>
@endsection
