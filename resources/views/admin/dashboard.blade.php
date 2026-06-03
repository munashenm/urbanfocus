@extends('layouts.admin')

@section('page_title', 'Dashboard')

@section('content')
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('admin.products.index') }}" class="stat-link">
            <div class="stat-card"><div class="text-muted small">Products</div><div class="value">{{ $stats['products'] }}</div></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('admin.orders.index') }}" class="stat-link">
            <div class="stat-card"><div class="text-muted small">Orders</div><div class="value">{{ $stats['orders'] }}</div></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="stat-card"><div class="text-muted small">Customers</div><div class="value">{{ $stats['customers'] }}</div></div>
    </div>
    <div class="col-6 col-md-6 col-lg-3">
        <div class="stat-card"><div class="text-muted small">Revenue (paid)</div><div class="value">R {{ number_format($stats['revenue'], 0) }}</div></div>
    </div>
    <div class="col-6 col-md-6 col-lg-3">
        <a href="{{ route('admin.orders.index') }}?status=pending" class="stat-link">
            <div class="stat-card"><div class="text-muted small">Pending orders</div><div class="value">{{ $stats['pending_orders'] }}</div></div>
        </a>
    </div>
    @if(Route::has('admin.quotations.index'))
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('admin.quotations.index') }}" class="stat-link">
            <div class="stat-card"><div class="text-muted small">Quotations</div><div class="value">{{ $stats['quotations'] }}</div></div>
        </a>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <a href="{{ route('admin.quotes.index') }}?status=new" class="stat-link">
            <div class="stat-card"><div class="text-muted small">New enquiries</div><div class="value">{{ $stats['new_enquiries'] }}</div></div>
        </a>
    </div>
    @endif
</div>

<div class="row g-4 mb-4">
    <div class="col-lg-4">
        <div class="card admin-card h-100">
            <div class="card-header bg-white fw-semibold">Quick actions</div>
            <div class="card-body admin-quick-actions d-grid gap-1">
                @if(Route::has('admin.quotations.create'))
                    <a href="{{ route('admin.quotations.create') }}" class="btn btn-primary btn-sm">New quotation</a>
                @endif
                <a href="{{ route('admin.products.create') }}" class="btn btn-outline-secondary btn-sm">Add product</a>
                <a href="{{ route('admin.orders.index') }}" class="btn btn-outline-secondary btn-sm">View orders</a>
                @if(Route::has('admin.catalog.index'))
                    <a href="{{ route('admin.catalog.index') }}" class="btn btn-outline-secondary btn-sm">Catalog &amp; feeds</a>
                @endif
            </div>
        </div>
    </div>
    @if(Route::has('admin.quotations.index') && $recentQuotations->isNotEmpty())
    <div class="col-lg-8">
        <h2 class="h6 fw-bold mb-2">Recent quotations</h2>
        <div class="card admin-card">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead><tr><th>Number</th><th>Customer</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach($recentQuotations as $q)
                            <tr>
                                <td><a href="{{ route('admin.quotations.show', $q) }}">{{ $q->quotation_number }}</a></td>
                                <td>{{ $q->customer_name }}</td>
                                <td>R {{ number_format($q->total, 2) }}</td>
                                <td><span class="badge bg-secondary">{{ $q->statusLabel() }}</span></td>
                                <td class="text-end"><a href="{{ route('admin.quotations.print', $q) }}" class="btn btn-sm btn-outline-secondary" target="_blank">Print</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<h2 class="h6 fw-bold mb-2">Recent orders</h2>
<div class="card admin-card">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer_email }}</td>
                        <td>R {{ number_format($order->total, 2) }}</td>
                        <td>{{ ucfirst($order->status) }}</td>
                        <td class="text-muted small">{{ $order->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="admin-empty">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
