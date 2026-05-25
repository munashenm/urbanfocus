@extends('layouts.admin')

@section('page_title', 'Categories')

@section('content')
<div class="d-flex justify-content-end mb-4">
    <a href="{{ route('admin.categories.create') }}" class="btn btn-primary btn-sm">Add Category</a>
</div>

<form id="bulk-categories-form" method="POST" action="{{ route('admin.categories.bulk-destroy') }}" onsubmit="return confirm('Delete the selected categories? Products in those categories will become uncategorized.')">
    @csrf
    @include('partials.admin-bulk-bar', ['deleteLabel' => 'Delete selected categories'])
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table mb-0 align-middle">
            <thead>
                <tr>
                    <th style="width:2.5rem">
                        <input type="checkbox" class="form-check-input" id="bulk-select-all" aria-label="Select all categories on this page">
                    </th>
                    <th>Name</th>
                    <th>Slug</th>
                    <th>Parent</th>
                    <th>Products</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($categories as $category)
                    <tr>
                        <td>
                            <input type="checkbox" class="form-check-input bulk-select" form="bulk-categories-form" name="ids[]" value="{{ $category->id }}" aria-label="Select {{ $category->name }}">
                        </td>
                            <td>{{ $category->name }}</td>
                            <td>{{ $category->slug }}</td>
                            <td>{{ $category->parent?->name ?? '—' }}</td>
                            <td>{{ $category->products_count }}</td>
                            <td>@if($category->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
                            <td class="text-end text-nowrap">
                                <a href="{{ route('admin.categories.edit', $category) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                <form action="{{ route('admin.categories.destroy', $category) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this category? Products will become uncategorized.')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-muted text-center py-4">No categories found.</td></tr>
                    @endforelse
            </tbody>
        </table>
    </div>
</div>

<div class="mt-3">{{ $categories->links() }}</div>
<p class="small text-muted mt-2">Bulk select applies to the current page only ({{ $categories->count() }} of {{ $categories->total() }} shown).</p>
@endsection
