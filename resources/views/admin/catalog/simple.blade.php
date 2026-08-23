@extends('layouts.admin')

@section('page_title', 'Catalog')

@section('content')
<div class="card border-success"><div class="card-body">
    <h2 class="h5 fw-bold text-success">Add target-range products</h2>
    @if(!empty($error))
        <div class="alert alert-warning small">The full catalog page failed to load ({{ $error }}). You can still add products here.</div>
    @endif
    <p class="small text-muted">Adds up to {{ number_format($targetRangeCount ?? 0) }} curated SKUs with full professional descriptions. Re-run to refresh copy, attach photos and apply the {{ rtrim(rtrim(number_format(config('pricing.target_range_topup_percent', 15), 1), '0'), '.') }}% price top-up on products we added.</p>
    <form action="{{ url('/admin/catalog/sync-target-range') }}" method="POST" onsubmit="return confirm('Create missing target-range products, refresh professional descriptions, and apply the catalogue price top-up on listings we added?')">
        @csrf
        <button type="submit" class="btn btn-success">Add missing products / update prices</button>
    </form>
</div></div>

<div class="card border-dark mt-4"><div class="card-body">
    <h2 class="h5 fw-bold">Add specialist technology products</h2>
    <p class="small text-muted">Adds up to {{ number_format($specialistCount ?? 0) }} specialist SKUs with South African SEO copy, Google Shopping fields and availability labels. Re-run to refresh copy, attach photos and apply the {{ rtrim(rtrim(number_format(config('pricing.specialist_topup_percent', 15), 1), '0'), '.') }}% price top-up on products we added.</p>
    <form action="{{ url('/admin/catalog/sync-specialist') }}" method="POST" onsubmit="return confirm('Create missing specialist products, refresh SEO descriptions, and apply the specialist price top-up on listings we added?')">
        @csrf
        <button type="submit" class="btn btn-dark">Add specialist products</button>
    </form>
</div></div>
@endsection
