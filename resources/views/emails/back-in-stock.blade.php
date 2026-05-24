<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">
    <div style="background:#0a1628;color:#fff;padding:20px;text-align:center">
        <img src="{{ config('app.url') }}/images/logo-stacked.png" alt="Urban Focus" style="max-height:60px;margin-bottom:8px">
    </div>
    <div style="padding:24px">
        <h2>Back in Stock</h2>
        @if($alert->name)
            <p>Hi {{ $alert->name }},</p>
        @else
            <p>Hi,</p>
        @endif
        <p>Good news — <strong>{{ $product->name }}</strong> is back in stock at Urban Focus.</p>

        @if($product->primary_image_url)
            <p style="text-align:center;margin:20px 0">
                <img src="{{ $product->primary_image_url }}" alt="{{ $product->name }}" style="max-width:200px;height:auto">
            </p>
        @endif

        <p style="font-size:18px"><strong>R {{ number_format($product->effective_price, 2) }}</strong> incl. VAT</p>

        <p style="margin:24px 0">
            <a href="{{ route('products.show', $product) }}" style="display:inline-block;background:#0d6efd;color:#fff;padding:12px 24px;text-decoration:none;border-radius:4px">View Product</a>
        </p>

        <p style="color:#666;font-size:13px">You received this email because you asked to be notified when this product was available again.</p>
    </div>
</body>
</html>
