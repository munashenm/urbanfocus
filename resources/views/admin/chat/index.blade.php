@extends('layouts.admin')
@section('page_title', 'Customer chat')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Customer chat</h1>
        <p class="text-muted mb-0">Messages from the website chat. Requests stay unapproved until someone on the team decides.</p>
    </div>
</div>
<form class="row g-2 mb-3" method="get" action="{{ route('admin.chat.index') }}">
    <div class="col-auto">
        <label class="visually-hidden" for="chat-status">Status</label>
        <select id="chat-status" name="status" class="form-select form-select-sm">
            <option value="">All statuses</option>
            @foreach(['open' => 'Open', 'waiting_staff' => 'Waiting for staff', 'closed' => 'Closed'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <label class="visually-hidden" for="chat-topic">Topic</label>
        <select id="chat-topic" name="topic" class="form-select form-select-sm">
            <option value="">All topics</option>
            @foreach(['general' => 'General', 'product' => 'Product', 'tracking' => 'Order tracking', 'return' => 'Return', 'warranty' => 'Warranty', 'support' => 'Support'] as $value => $label)
                <option value="{{ $value }}" @selected(request('topic') === $value)>{{ $label }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-auto">
        <button class="btn btn-sm btn-outline-primary" type="submit">Filter</button>
    </div>
</form>
<div class="card"><div class="table-responsive"><table class="table mb-0">
<thead><tr><th>Last activity</th><th>Topic</th><th>Status</th><th>Customer</th><th>Latest</th><th></th></tr></thead>
<tbody>
@forelse($conversations as $conversation)
<tr>
    <td>{{ $conversation->last_message_at?->timezone(config('chat.timezone'))->format('d M Y H:i') ?? '—' }}</td>
    <td>{{ $conversation->topicLabel() }}</td>
    <td>
        <span class="badge bg-light text-dark">{{ $conversation->statusLabel() }}</span>
        @if($conversation->needsStaffAttention())
            <span class="badge bg-warning text-dark">Unread</span>
        @endif
    </td>
    <td>{{ $conversation->visitor_name ?: 'Website visitor' }}@if($conversation->visitor_email)<br><span class="text-muted">{{ $conversation->visitor_email }}</span>@endif</td>
    <td class="text-muted">{{ \Illuminate\Support\Str::limit($conversation->latestMessage->body ?? '', 80) }}</td>
    <td class="text-end"><a href="{{ route('admin.chat.show', $conversation) }}" class="btn btn-sm btn-outline-primary">Open</a></td>
</tr>
@empty
<tr><td colspan="6" class="text-muted">No customer chats yet.</td></tr>
@endforelse
</tbody>
</table></div></div>
<div class="mt-3">{{ $conversations->links() }}</div>
@endsection
