<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;color:#333;max-width:640px;margin:0 auto">
    <div style="background:#0a1628;color:#fff;padding:20px">
        <h2 style="margin:0">Weekly customer chat summary</h2>
    </div>
    <div style="padding:24px">
        <p>{{ $report['since']->timezone(config('chat.timezone'))->format('d M Y H:i') }}
            to {{ $report['until']->timezone(config('chat.timezone'))->format('d M Y H:i') }} (Africa/Johannesburg).</p>
        <table width="100%" cellpadding="6" cellspacing="0" style="border-collapse:collapse">
            <tr><td style="color:#666">Conversations started</td><td><strong>{{ $report['conversations'] }}</strong></td></tr>
            <tr><td style="color:#666">Handovers to the team</td><td><strong>{{ $report['handovers'] }}</strong></td></tr>
            <tr><td style="color:#666">Quotes submitted from chat</td><td><strong>{{ $report['quotes'] }}</strong></td></tr>
        </table>

        <h3>Topics</h3>
        @forelse($report['topics'] as $topic)
            <p style="margin:0 0 6px">{{ $topic['topic'] ?: 'general' }} — {{ $topic['total'] }}</p>
        @empty
            <p>No conversations in this week.</p>
        @endforelse

        <h3>Top questions</h3>
        @forelse($report['questions'] as $question => $total)
            <p style="margin:0 0 6px">{{ $total }} × {{ $question }}</p>
        @empty
            <p>No visitor questions in this week.</p>
        @endforelse

        <h3>Questions the bot could not answer</h3>
        @forelse($report['unanswered'] as $question)
            <p style="margin:0 0 6px">{{ $question }}</p>
        @empty
            <p>None logged.</p>
        @endforelse

        <h3>Products searched for but not found</h3>
        @forelse($report['not_found'] as $question)
            <p style="margin:0 0 6px">{{ $question }}</p>
        @empty
            <p>None logged.</p>
        @endforelse
    </div>
</body>
</html>
