@extends('layouts.admin')

@section('page_title', 'Reports')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="text-muted small">Total sales</div><div class="value">R {{ number_format($salesTotal, 0) }}</div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="text-muted small">Orders</div><div class="value">{{ $ordersCount }}</div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="text-muted small">Customers</div><div class="value">{{ $customersCount }}</div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="text-muted small">RFQs</div><div class="value">{{ $rfqCount }}</div></div></div>
    <div class="col-md-2"><div class="stat-card"><div class="text-muted small">Low stock</div><div class="value">{{ $lowStockCount }}</div></div></div>
</div>

<div class="card admin-card">
    <div class="card-header bg-white fw-semibold">Export reports (CSV)</div>
    <div class="card-body d-flex flex-wrap gap-2">
        <a href="{{ route('admin.reports.export', ['type' => 'sales']) }}" class="btn btn-outline-primary btn-sm">Sales</a>
        <a href="{{ route('admin.reports.export', ['type' => 'products']) }}" class="btn btn-outline-primary btn-sm">Products</a>
        <a href="{{ route('admin.reports.export', ['type' => 'customers']) }}" class="btn btn-outline-primary btn-sm">Customers</a>
        <a href="{{ route('admin.reports.export', ['type' => 'stock']) }}" class="btn btn-outline-primary btn-sm">Stock</a>
        <a href="{{ route('admin.reports.export', ['type' => 'rfqs']) }}" class="btn btn-outline-primary btn-sm">RFQs</a>
    </div>
</div>
@endsection
