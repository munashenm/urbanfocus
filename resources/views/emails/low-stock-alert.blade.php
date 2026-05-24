<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">
    <div style="background:#0a1628;color:#fff;padding:20px;text-align:center">
        <img src="{{ config('app.url') }}/images/logo-stacked.png" alt="Urban Focus" style="max-height:60px;margin-bottom:8px">
    </div>
    <div style="padding:24px">
        <h2>Low Stock Alert</h2>
        <p>The following product has dropped to low stock levels:</p>

        <div style="background:#fff3cd;padding:16px;border-radius:6px;margin:16px 0">
            <p style="margin:0 0 8px"><strong>{{ $product->name }}</strong></p>
            @if($product->sku)
                <p style="margin:0 0 8px">SKU: {{ $product->sku }}</p>
            @endif
            <p style="margin:0;font-size:18px"><strong>{{ $product->stock_quantity }}</strong> units remaining</p>
            <p style="margin:8px 0 0;color:#666;font-size:13px">Threshold: {{ config('inventory.low_stock_threshold', 5) }} units</p>
        </div>

        <p>
            <a href="{{ route('admin.products.edit', $product) }}" style="display:inline-block;background:#0d6efd;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">Edit Product in Admin</a>
        </p>
    </div>
</body>
</html>
