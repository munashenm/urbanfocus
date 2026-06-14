@extends('layouts.admin')

@section('page_title', 'Categories')

@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <p class="text-muted small mb-0">9 main categories with subcategories. Products should be assigned to a subcategory where possible.</p>
    <div class="d-flex gap-2">
        <a href="{{ route('admin.categories.create') }}" class="btn btn-outline-primary btn-sm">Add main category</a>
        <a href="{{ route('admin.categories.create', ['parent_id' => $parents->first()?->id]) }}" class="btn btn-primary btn-sm">Add subcategory</a>
    </div>
</div>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Slug</th>
                    <th>Products</th>
                    <th>Order</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($parents as $parent)
                    <tr class="table-light">
                        <td class="fw-semibold">
                            {{ $parent->name }}
                            <span class="badge bg-primary-subtle text-primary-emphasis ms-1">Main</span>
                        </td>
                        <td><code>{{ $parent->slug }}</code></td>
                        <td>{{ $parent->products_count }}</td>
                        <td>{{ $parent->sort_order }}</td>
                        <td>@if($parent->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Disabled</span>@endif</td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.categories.create', ['parent_id' => $parent->id]) }}" class="btn btn-sm btn-outline-secondary">Add sub</a>
                            <a href="{{ route('admin.categories.edit', $parent) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                        </td>
                    </tr>
                    @foreach($parent->children as $child)
                        <tr>
                            <td class="ps-4">↳ {{ $child->name }}</td>
                            <td><code>{{ $parent->slug }}/{{ $child->slug }}</code></td>
                            <td>{{ $child->products_count }}</td>
                            <td>{{ $child->sort_order }}</td>
                            <td>@if($child->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Disabled</span>@endif</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.categories.edit', $child) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            </td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="6" class="text-muted text-center py-4">No categories found. Run <code>php artisan categories:reorganize --run</code> to seed the canonical tree.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
