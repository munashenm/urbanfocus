@extends('layouts.admin')

@section('page_title', 'Orders')

@section('content')
<div class="admin-page-actions">
    <form class="admin-filters" method="GET">
        <input type="search" name="q" class="form-control form-control-sm" placeholder="Search orders…" value="{{ request('q') }}">
        <select name="status" class="form-select form-select-sm">
            <option value="">All statuses</option>
            @foreach($statuses as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="payment_status" class="form-select form-select-sm">
            <option value="">All payments</option>
            @foreach($paymentStatuses as $value => $label)
                <option value="{{ $value }}" @selected(request('payment_status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn btn-sm btn-outline-secondary">Filter</button>
    </form>
</div>

<div class="card admin-card admin-data-table">
    <div class="table-responsive">
        <table class="table table-hover mb-0 align-middle">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer_name ?: $order->customer_email }}</td>
                        <td>R {{ number_format($order->total, 2) }}</td>
                        <td>@include('partials.admin-status-badge', ['status' => $order->payment_status])</td>
                        <td>@include('partials.admin-status-badge', ['status' => $order->status])</td>
                        <td>{{ $order->created_at->format('d M Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
