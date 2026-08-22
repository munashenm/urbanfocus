@extends('layouts.admin')

@section('page_title', 'Catalog')

@section('content')
<div class="card border-success"><div class="card-body">
    <h2 class="h5 fw-bold text-success">Add target-range products</h2>
    @if(!empty($error))
        <div class="alert alert-warning small">The full catalog page failed to load ({{ $error }}). You can still add products here.</div>
    @endif
    <p class="small text-muted">Adds up to {{ number_format($targetRangeCount ?? 0) }} curated SKUs. Existing listings are skipped. Re-run to attach missing photos and apply the {{ rtrim(rtrim(number_format(config('pricing.target_range_topup_percent', 10), 1), '0'), '.') }}% price top-up on products we added.</p>
    <form action="{{ url('/admin/catalog/sync-target-range') }}" method="POST" onsubmit="return confirm('Create missing target-range products, attach photos, and apply the 10% price top-up on listings we added?')">
        @csrf
        <button type="submit" class="btn btn-success">Add missing products / update prices</button>
    </form>
</div></div>
@endsection
