<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">
    <div style="background:#0a1628;color:#fff;padding:20px">
        <h2 style="margin:0">Customer chat</h2>
    </div>
    <div style="padding:24px">
        <p>{{ $conversation->topicLabel() }} · {{ $conversation->statusLabel() }}</p>
        <p style="white-space:pre-wrap">{{ $message->body }}</p>
        @if($message->payload)
            <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
                @foreach($message->payload as $label => $value)
                    @if(filled($value))
                        <tr>
                            <td style="color:#666;width:140px">{{ str_replace('_', ' ', $label) }}</td>
                            <td>{{ $value }}</td>
                        </tr>
                    @endif
                @endforeach
            </table>
        @endif
        <p style="margin-top:24px">
            <a href="{{ route('admin.chat.show', $conversation) }}">Open this conversation in Customer chat</a>
        </p>
        <p style="color:#666;font-size:13px">This notice does not approve a return, refund or warranty claim.</p>
    </div>
</body>
</html>
