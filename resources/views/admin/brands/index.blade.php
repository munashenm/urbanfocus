@extends('layouts.admin')
@section('page_title', 'Brands')
@section('content')
<div class="d-flex justify-content-end mb-4"><a href="{{ route('admin.brands.create') }}" class="btn btn-primary btn-sm">Add Brand</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Logo</th><th>Name</th><th>Slug</th><th>Status</th><th></th></tr></thead>
<tbody>@foreach($brands as $brand)<tr>
<td>@if($brand->logo)<img src="{{ asset($brand->logo) }}" alt="" height="28">@else—@endif</td>
<td>{{ $brand->name }}</td><td>{{ $brand->slug }}</td>
<td>@if($brand->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
<td class="text-end"><a href="{{ route('admin.brands.edit', $brand) }}" class="btn btn-sm btn-outline-primary">Edit</a>
<form action="{{ route('admin.brands.destroy', $brand) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
</tr>@endforeach</tbody></table></div></div>
<div class="mt-3">{{ $brands->links() }}</div>
@endsection
