<!DOCTYPE html>
<html>
<head><title>Redirecting to PayFast...</title></head>
<body onload="document.forms[0].submit()">
    <p>Redirecting to PayFast secure payment...</p>
    <form action="{{ $processUrl }}" method="POST">
        @foreach($paymentData as $key => $value)
            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
        @endforeach
        <noscript><button type="submit">Continue to PayFast</button></noscript>
    </form>
</body>
</html>
