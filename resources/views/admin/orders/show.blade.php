@extends('layouts.admin')

@section('page_title', 'Order '.$order->order_number)

@section('content')
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4"><div class="card-body">
            <h3 class="h6 fw-bold">Order Items</h3>
            <table class="table">
                <thead><tr><th>Product</th><th>SKU</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ $item->product_sku }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>R {{ number_format($item->unit_price, 2) }}</td>
                            <td>R {{ number_format($item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
        <div class="card"><div class="card-body">
            <h3 class="h6 fw-bold">Customer Details</h3>
            <p>{{ $order->customer_name }}<br>{{ $order->customer_email }}<br>{{ $order->customer_phone }}</p>
            <p>{{ $order->billing_address_line_1 }}<br>{{ $order->billing_city }}, {{ $order->billing_province }} {{ $order->billing_postal_code }}</p>
        </div></div>
    </div>
    <div class="col-lg-4">
        <div class="card"><div class="card-body">
            <form action="{{ route('admin.orders.update', $order) }}" method="POST">
                @csrf @method('PUT')
                <div class="mb-3"><label class="form-label">Order Status</label>
                    <select name="status" class="form-select">
                        @foreach(['pending','processing','shipped','completed','cancelled'] as $s)
                            <option value="{{ $s }}" @selected($order->status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Payment Status</label>
                    <select name="payment_status" class="form-select">
                        @foreach(['pending','paid','failed','refunded'] as $s)
                            <option value="{{ $s }}" @selected($order->payment_status === $s)>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-3"><label class="form-label">Admin Notes</label><textarea name="admin_notes" class="form-control" rows="3">{{ $order->admin_notes }}</textarea></div>
                <hr>
                <p>Subtotal: R {{ number_format($order->subtotal, 2) }}</p>
                <p>Shipping: R {{ number_format($order->shipping_cost, 2) }}</p>
                <p>VAT: R {{ number_format($order->tax_amount, 2) }}</p>
                <p class="fw-bold">Total: R {{ number_format($order->total, 2) }}</p>
                <button type="submit" class="btn btn-primary w-100">Update Order</button>
            </form>
        </div></div>
    </div>
</div>
@endsection
