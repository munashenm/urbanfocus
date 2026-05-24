<!DOCTYPE html>
<html><body style="font-family:Arial,sans-serif;color:#1a1f2e">
<h2>New Order: {{ $order->order_number }}</h2>
<p><strong>Customer:</strong> {{ $order->billing_first_name }} {{ $order->billing_last_name }} ({{ $order->customer_email }})</p>
<p><strong>Total:</strong> R {{ number_format($order->total, 2) }}</p>
<p><strong>Payment:</strong> {{ strtoupper($order->payment_method) }} — {{ $order->payment_status }}</p>
<p><a href="{{ url('/admin/orders/'.$order->id) }}">View in Admin</a></p>
</body></html>
