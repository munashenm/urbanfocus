@extends('layouts.admin')
@section('page_title', 'Coupons')
@section('content')
<div class="d-flex justify-content-end mb-4"><a href="{{ route('admin.coupons.create') }}" class="btn btn-primary btn-sm">Add Coupon</a></div>
<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Uses</th><th>Status</th><th></th></tr></thead>
<tbody>@foreach($coupons as $coupon)<tr>
<td><code>{{ $coupon->code }}</code></td><td>{{ $coupon->type }}</td><td>{{ $coupon->type==='percent' ? $coupon->value.'%' : 'R '.$coupon->value }}</td>
<td>{{ $coupon->used_count }}{{ $coupon->max_uses ? '/'.$coupon->max_uses : '' }}</td>
<td>@if($coupon->is_active)<span class="badge bg-success">Active</span>@else<span class="badge bg-secondary">Inactive</span>@endif</td>
<td class="text-end"><a href="{{ route('admin.coupons.edit', $coupon) }}" class="btn btn-sm btn-outline-primary">Edit</a>
<form action="{{ route('admin.coupons.destroy', $coupon) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form></td>
</tr>@endforeach</tbody></table></div></div>
<div class="mt-3">{{ $coupons->links() }}</div>
@endsection
