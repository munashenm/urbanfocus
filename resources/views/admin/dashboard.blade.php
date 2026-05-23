@extends('layouts.admin')

@section('page_title', 'Dashboard')

@section('content')
<div class="row g-4 mb-4">
    <div class="col-md-4 col-lg-2"><div class="stat-card"><div class="text-muted small">Products</div><div class="value">{{ $stats['products'] }}</div></div></div>
    <div class="col-md-4 col-lg-2"><div class="stat-card"><div class="text-muted small">Orders</div><div class="value">{{ $stats['orders'] }}</div></div></div>
    <div class="col-md-4 col-lg-2"><div class="stat-card"><div class="text-muted small">Customers</div><div class="value">{{ $stats['customers'] }}</div></div></div>
    <div class="col-md-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Revenue (Paid)</div><div class="value">R {{ number_format($stats['revenue'], 0) }}</div></div></div>
    <div class="col-md-6 col-lg-3"><div class="stat-card"><div class="text-muted small">Pending Orders</div><div class="value">{{ $stats['pending_orders'] }}</div></div></div>
</div>

<h2 class="h5 fw-bold mb-3">Recent Orders</h2>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @forelse($recentOrders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer_email }}</td>
                        <td>R {{ number_format($order->total, 2) }}</td>
                        <td>{{ ucfirst($order->status) }}</td>
                        <td>{{ $order->created_at->format('d M Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted">No orders yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
