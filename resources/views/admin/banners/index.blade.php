@extends('layouts.admin')
@section('page_title', 'Banners')
@section('content')
<div class="d-flex justify-content-end mb-4"><a href="{{ route('admin.banners.create') }}" class="btn btn-primary btn-sm">Add Banner</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th></th><th>Title</th><th>Placement</th><th>Order</th><th>Status</th><th></th></tr></thead>
<tbody>@foreach($banners as $banner)<tr>
<td>
    @if($banner->image)
        <img src="{{ str_starts_with($banner->image, 'http') ? $banner->image : storage_public_url($banner->image) }}" alt="" class="admin-product-thumb" width="48" height="48" loading="lazy">
    @else
        <span class="admin-product-thumb admin-product-thumb--empty">🖼️</span>
    @endif
</td>
<td>{{ $banner->title }}</td><td>{{ $banner->placement }}</td><td>{{ $banner->sort_order }}</td>
<td>@if($banner->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
<td class="text-end"><a href="{{ route('admin.banners.edit', $banner) }}" class="btn btn-sm btn-outline-primary">Edit</a>
<form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
</tr>@endforeach</tbody></table></div></div>
<div class="mt-3">{{ $banners->links() }}</div>
@endsection
