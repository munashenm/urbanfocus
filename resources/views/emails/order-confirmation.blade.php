<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">
    <div style="background:#0a1628;color:#fff;padding:20px;text-align:center">
        <img src="{{ config('app.url') }}/images/logo-stacked.png" alt="Urban Focus" style="max-height:60px;margin-bottom:8px">
    </div>
    <div style="padding:24px">
        <h2>Order Confirmation</h2>
        <p>Hi {{ $order->customer_name }},</p>
        <p>Thank you for your order. We've received order <strong>{{ $order->order_number }}</strong>.</p>

        <table width="100%" cellpadding="8" cellspacing="0" style="border-collapse:collapse;margin:20px 0">
            <thead>
                <tr style="background:#f5f7fa">
                    <th align="left">Product</th>
                    <th align="center">Qty</th>
                    <th align="right">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($order->items as $item)
                    <tr style="border-bottom:1px solid #eee">
                        <td>{{ $item->product_name }}</td>
                        <td align="center">{{ $item->quantity }}</td>
                        <td align="right">R {{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p><strong>Subtotal:</strong> R {{ number_format($order->subtotal, 2) }}</p>
        <p><strong>Shipping:</strong> R {{ number_format($order->shipping_cost, 2) }}</p>
        <p><strong>VAT:</strong> R {{ number_format($order->tax_amount, 2) }}</p>
        <p><strong>Total:</strong> R {{ number_format($order->total, 2) }}</p>

        @if($order->payment_method === 'eft')
            <p style="background:#e8f4fd;padding:12px;border-radius:6px">
                Please use order number <strong>{{ $order->order_number }}</strong> as your EFT reference.
            </p>
        @endif

        <p>Questions? Contact us at <a href="mailto:sales@urbanfocus.co.za">sales@urbanfocus.co.za</a> or 087 550 1813.</p>
    </div>
</body>
</html>
