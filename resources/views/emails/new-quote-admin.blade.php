<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">
    <div style="background:#0a1628;color:#fff;padding:20px">
        <h2 style="margin:0">New {{ $quote->typeLabel() }}</h2>
    </div>
    <div style="padding:24px">
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
            <tr><td style="color:#666;width:140px">Type</td><td><strong>{{ $quote->typeLabel() }}</strong></td></tr>
            <tr><td style="color:#666">Name</td><td>{{ $quote->name }}</td></tr>
            @if($quote->company)
                <tr><td style="color:#666">Company</td><td>{{ $quote->company }}</td></tr>
            @endif
            <tr><td style="color:#666">Email</td><td><a href="mailto:{{ $quote->email }}">{{ $quote->email }}</a></td></tr>
            @if($quote->phone)
                <tr><td style="color:#666">Phone</td><td>{{ $quote->phone }}</td></tr>
            @endif
            @if($quote->product)
                <tr><td style="color:#666">Product</td><td>{{ $quote->product->name }}</td></tr>
            @endif
        </table>

        @if($quote->message)
            <h3 style="margin-top:24px">Message</h3>
            <p style="white-space:pre-wrap;background:#f5f7fa;padding:12px;border-radius:6px">{{ $quote->message }}</p>
        @endif

        @if($quote->file_path)
            <p style="margin-top:16px">
                <strong>RFQ file:</strong>
                <a href="{{ url('/storage/'.$quote->file_path) }}">Download attachment</a>
            </p>
        @endif

        <p style="margin-top:24px">
            <a href="{{ route('admin.quotes.show', $quote) }}" style="display:inline-block;background:#0a1628;color:#fff;padding:10px 18px;text-decoration:none;border-radius:6px">View in Admin</a>
        </p>
    </div>
</body>
</html>
