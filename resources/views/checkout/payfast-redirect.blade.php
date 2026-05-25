<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to PayFast | Urban Focus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f8fafc; }
        .redirect-card { max-width: 420px; text-align: center; padding: 2.5rem; background: #fff; border-radius: 12px; box-shadow: 0 8px 32px rgba(10,22,40,0.08); }
    </style>
</head>
<body onload="document.forms[0].submit()">
    <div class="redirect-card">
        <img src="{{ asset('images/logo.png') }}" alt="Urban Focus" width="180" height="38" class="mb-4">
        <div class="spinner-border text-primary mb-3" role="status"><span class="visually-hidden">Loading</span></div>
        <h1 class="h5 fw-bold">Redirecting to PayFast</h1>
        <p class="text-muted small mb-0">Secure payment for order <strong>{{ $order->order_number }}</strong>. Please wait…</p>
        <form action="{{ $processUrl }}" method="POST" class="mt-3">
            @foreach($paymentData as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <noscript><button type="submit" class="btn btn-primary">Continue to PayFast</button></noscript>
        </form>
    </div>
</body>
</html>
