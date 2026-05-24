@extends('layouts.admin')
@section('page_title', $coupon->exists ? 'Edit Coupon' : 'Add Coupon')
@section('content')
<form action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" method="POST" class="card"><div class="card-body">
@csrf @if($coupon->exists) @method('PUT') @endif
<div class="mb-3"><label class="form-label">Code *</label><input type="text" name="code" class="form-control" value="{{ old('code', $coupon->code) }}" required></div>
<div class="mb-3"><label class="form-label">Type</label><select name="type" class="form-select"><option value="percent" @selected(old('type',$coupon->type)==='percent')>Percentage</option><option value="fixed" @selected(old('type',$coupon->type)==='fixed')>Fixed Amount</option></select></div>
<div class="mb-3"><label class="form-label">Value *</label><input type="number" step="0.01" name="value" class="form-control" value="{{ old('value', $coupon->value) }}" required></div>
<div class="mb-3"><label class="form-label">Minimum Order (ZAR)</label><input type="number" step="0.01" name="min_order" class="form-control" value="{{ old('min_order', $coupon->min_order) }}"></div>
<div class="mb-3"><label class="form-label">Max Uses</label><input type="number" name="max_uses" class="form-control" value="{{ old('max_uses', $coupon->max_uses) }}"></div>
<div class="form-check mb-3"><input type="hidden" name="is_active" value="0"><input type="checkbox" name="is_active" value="1" class="form-check-input" @checked(old('is_active', $coupon->is_active ?? true))><label class="form-check-label">Active</label></div>
<button type="submit" class="btn btn-primary">Save Coupon</button>
</div></form>
@endsection
