<div class="row g-4">
    <div class="col-lg-8">
        <div class="checkout-card">
            <h2 class="h5 fw-bold mb-3">Items</h2>
            <table class="table">
                <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->product_name }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>R {{ number_format($item->unit_price, 2) }}</td>
                            <td>R {{ number_format($item->line_total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="checkout-card mb-4">
            <p><strong>Status:</strong> {{ ucfirst($order->status) }}</p>
            <p><strong>Payment:</strong> {{ ucfirst($order->payment_status) }} ({{ strtoupper($order->payment_method) }})</p>
            <p class="small text-muted mb-0">Placed {{ $order->created_at->format('d M Y H:i') }}</p>
            @auth
                @if(auth()->user()->isAdmin() || ($order->isPaid() && auth()->id() === $order->user_id))
                    <p class="mt-3 mb-0">
                        <a href="{{ route('orders.invoice', $order) }}" class="btn btn-outline-primary btn-sm" target="_blank">{{ $order->isPaid() ? 'View Invoice' : 'View Proforma' }}</a>
                    </p>
                @elseif(auth()->id() === $order->user_id && ! $order->isPaid())
                    <p class="mt-3 mb-0 small text-muted">Your tax invoice will be available once payment is confirmed.</p>
                @endif
            @endauth
            <hr>
            <p class="mb-1">Subtotal: R {{ number_format($order->subtotal, 2) }}</p>
            @if($order->discount_amount > 0)
                <p class="mb-1 text-success">Discount: −R {{ number_format($order->discount_amount, 2) }}</p>
            @endif
            <p class="mb-1">Shipping: R {{ number_format($order->shipping_cost, 2) }}</p>
            <p class="mb-1">VAT: R {{ number_format($order->tax_amount, 2) }}</p>
            <p class="h5 mb-0">Total: R {{ number_format($order->total, 2) }}</p>
        </div>

        @if($order->payment_method === 'eft' && $order->payment_status !== 'paid')
            <div class="checkout-card">
                @include('partials.eft-instructions', ['order' => $order])
            </div>
        @endif
    </div>
</div>
