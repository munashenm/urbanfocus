<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:600px;margin:0 auto">
    <div style="background:#0a1628;color:#fff;padding:20px">
        <h2 style="margin:0">A visitor wants to talk to the team</h2>
    </div>
    <div style="padding:24px">
        <p>This conversation is waiting in Customer chat. A reply is not automatic.</p>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
            <tr><td style="color:#666;width:140px">Name</td><td>{{ $conversation->visitor_name ?: 'Not given' }}</td></tr>
            <tr><td style="color:#666">Email</td><td>{{ $conversation->visitor_email ?: 'Not given' }}</td></tr>
            @if($conversation->visitor_phone)
                <tr><td style="color:#666">Phone</td><td>{{ $conversation->visitor_phone }}</td></tr>
            @endif
            <tr><td style="color:#666">Topic</td><td>{{ $conversation->topicLabel() }}</td></tr>
        </table>
        <h3 style="margin-top:24px">Latest messages</h3>
        @foreach($recentMessages as $message)
            <p style="margin:0 0 12px">
                <strong>{{ $message->senderLabel() }}</strong>
                <span style="color:#666">{{ $message->created_at?->timezone(config('chat.timezone'))->format('d M H:i') }}</span><br>
                <span style="white-space:pre-wrap">{{ $message->body }}</span>
            </p>
        @endforeach
        <p style="margin-top:24px">
            <a href="{{ route('admin.chat.show', $conversation) }}">Open this conversation</a>
        </p>
    </div>
</body>
</html>
