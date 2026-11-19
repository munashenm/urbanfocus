@extends('layouts.admin')
@section('page_title', 'Customer chat')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="{{ route('admin.chat.index') }}" class="text-decoration-none">← Customer chat</a>
        <h1 class="h4 mb-1 mt-2">{{ $conversation->topicLabel() }}</h1>
        <p class="text-muted mb-0">{{ $conversation->statusLabel() }}
            @if($conversation->visitor_name) · {{ $conversation->visitor_name }} @endif
            @if($conversation->visitor_email) · {{ $conversation->visitor_email }} @endif
            @if($conversation->visitor_phone) · {{ $conversation->visitor_phone }} @endif
        </p>
    </div>
    @if($conversation->status !== 'closed')
        <form method="post" action="{{ route('admin.chat.close', $conversation) }}">
            @csrf
            <button class="btn btn-outline-secondary btn-sm" type="submit">Close conversation</button>
        </form>
    @endif
</div>

<div class="card mb-3"><div class="card-body admin-chat-thread">
    @foreach($conversation->messages as $message)
        <article class="admin-chat-message admin-chat-message-{{ $message->sender }}">
            <header>
                <strong>{{ $message->senderLabel() }}</strong>
                @if($message->user)
                    <span class="text-muted">{{ $message->user->name }}</span>
                @endif
                <time class="text-muted" datetime="{{ $message->created_at?->toIso8601String() }}">{{ $message->created_at?->timezone(config('chat.timezone'))->format('d M Y H:i') }}</time>
                @if($message->review_status)
                    <span class="badge bg-light text-dark">{{ str_replace('_', ' ', $message->review_status) }}</span>
                @endif
            </header>
            <p class="mb-2" style="white-space:pre-wrap">{{ $message->body }}</p>
            @if($message->cards)
                <ul class="mb-2">
                    @foreach($message->cards as $card)
                        <li><a href="{{ $card['url'] ?? '#' }}">{{ $card['name'] ?? 'Product' }}</a> — {{ $card['price_label'] ?? '' }} — {{ $card['availability'] ?? '' }}</li>
                    @endforeach
                </ul>
            @endif
            @if($message->payload)
                <dl class="row small mb-2">
                    @foreach($message->payload as $label => $value)
                        @if(filled($value))
                            <dt class="col-sm-3">{{ str_replace('_', ' ', $label) }}</dt>
                            <dd class="col-sm-9">{{ $value }}</dd>
                        @endif
                    @endforeach
                </dl>
            @endif
            @if($message->review_status)
                <form class="d-flex flex-wrap gap-2 align-items-center" method="post" action="{{ route('admin.chat.requests.update', [$conversation, $message]) }}">
                    @csrf
                    <label class="small mb-0" for="review-{{ $message->id }}">Staff review</label>
                    <select id="review-{{ $message->id }}" name="review_status" class="form-select form-select-sm w-auto">
                        @foreach(['submitted' => 'Submitted', 'in_review' => 'In review', 'closed' => 'Closed'] as $value => $label)
                            <option value="{{ $value }}" @selected($message->review_status === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary" type="submit">Update</button>
                    <span class="small text-muted">Updating this does not approve a return, refund or warranty claim.</span>
                </form>
            @endif
        </article>
    @endforeach
</div></div>

@if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
@endif

<form method="post" action="{{ route('admin.chat.reply', $conversation) }}" class="card"><div class="card-body">
    @csrf
    <label for="chat-reply" class="form-label">Reply to the customer</label>
    <textarea id="chat-reply" name="body" class="form-control" rows="4" maxlength="5000" required>{{ old('body') }}</textarea>
    <button class="btn btn-primary mt-3" type="submit">Send reply</button>
</div></form>
@endsection
