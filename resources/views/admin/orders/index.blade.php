@extends('layouts.admin')

@section('page_title', 'Orders')

@section('content')
<form class="mb-3" method="GET">
    <select name="status" class="form-select form-select-sm" style="width:200px" onchange="this.form.submit()">
        <option value="">All Statuses</option>
        @foreach(['pending','processing','shipped','completed','cancelled'] as $s)
            <option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>
        @endforeach
    </select>
</form>
<div class="card">
    <div class="table-responsive">
        <table class="table mb-0">
            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
                @foreach($orders as $order)
                    <tr>
                        <td><a href="{{ route('admin.orders.show', $order) }}">{{ $order->order_number }}</a></td>
                        <td>{{ $order->customer_email }}</td>
                        <td>R {{ number_format($order->total, 2) }}</td>
                        <td>{{ ucfirst($order->payment_status) }}</td>
                        <td>{{ ucfirst($order->status) }}</td>
                        <td>{{ $order->created_at->format('d M Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
<div class="mt-3">{{ $orders->links() }}</div>
@endsection
