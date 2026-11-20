@extends('layouts.admin')
@section('page_title', 'Live Chat')
@section('content')
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">Live Chat</h1>
        <p class="text-muted mb-0">Queue and chats you have claimed. The assistant stays quiet while you own a chat.</p>
    </div>
    <div class="d-flex align-items-center gap-2">
        <span class="badge text-bg-secondary" id="live-chat-badge">Queue 0</span>
        <button type="button" class="btn btn-sm btn-outline-primary" id="live-chat-presence" data-status="{{ $presence->status }}">
            {{ $presence->status === 'online' ? 'You are online' : 'You are away' }}
        </button>
    </div>
</div>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header">Waiting</div>
            <ul class="list-group list-group-flush" id="live-chat-queue"></ul>
        </div>
        <div class="card">
            <div class="card-header">Your active chats</div>
            <ul class="list-group list-group-flush" id="live-chat-active"></ul>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between" id="live-chat-title">Select a chat</div>
            <div class="card-body" id="live-chat-log" style="min-height: 280px; max-height: 480px; overflow-y: auto;"></div>
            <div class="card-footer">
                <form id="live-chat-reply" class="d-flex gap-2">
                    <label class="visually-hidden" for="live-chat-body">Reply</label>
                    <textarea id="live-chat-body" class="form-control" rows="2" maxlength="5000" placeholder="Reply to the customer"></textarea>
                    <button class="btn btn-primary" type="submit">Send</button>
                </form>
                <div class="d-flex gap-2 mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="live-chat-handback">Hand back to assistant</button>
                    <button type="button" class="btn btn-sm btn-outline-danger" id="live-chat-close">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
@push('scripts')
<script>
(function () {
    var current = null;
    var lastQueue = 0;
    var csrf = document.querySelector('meta[name="csrf-token"]');
    var token = csrf ? csrf.getAttribute('content') : '';
    var badge = document.getElementById('live-chat-badge');
    var presence = document.getElementById('live-chat-presence');

    function beep() {
        try {
            var context = new (window.AudioContext || window.webkitAudioContext)();
            var tone = context.createOscillator();
            var gain = context.createGain();
            tone.frequency.value = 880;
            gain.gain.value = 0.04;
            tone.connect(gain);
            gain.connect(context.destination);
            tone.start();
            tone.stop(context.currentTime + 0.15);
        } catch (e) {}
    }

    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token
            },
            body: JSON.stringify(data || {})
        }).then(function (response) { return response.json(); });
    }

    function paintList(id, rows, action) {
        var list = document.getElementById(id);
        list.replaceChildren();
        if (!rows.length) {
            var empty = document.createElement('li');
            empty.className = 'list-group-item text-muted';
            empty.textContent = 'None';
            list.appendChild(empty);
            return;
        }
        rows.forEach(function (row) {
            var item = document.createElement('li');
            item.className = 'list-group-item';
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-link p-0 text-start';
            button.textContent = row.customer + ' — ' + (row.preview || '');
            button.addEventListener('click', function () { action(row); });
            item.appendChild(button);
            list.appendChild(item);
        });
    }

    function openTranscript(id) {
        current = id;
        fetch(@json(url('/admin/live-chat')) + '/' + id + '/transcript', { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(paintTranscript);
    }

    function paintTranscript(payload) {
        var title = document.getElementById('live-chat-title');
        var log = document.getElementById('live-chat-log');
        var chat = payload.conversation || {};
        title.textContent = (chat.customer || 'Chat') + (chat.agent ? ' · ' + chat.agent : '');
        log.replaceChildren();
        (payload.messages || []).forEach(function (message) {
            var line = document.createElement('p');
            line.className = 'mb-2';
            var who = document.createElement('strong');
            who.textContent = message.sender_name || message.sender;
            line.appendChild(who);
            line.appendChild(document.createTextNode(' ' + (message.body || '')));
            log.appendChild(line);
        });
        log.scrollTop = log.scrollHeight;
    }

    function refresh() {
        fetch(@json(route('admin.live-chat.feed')), { headers: { 'Accept': 'application/json' } })
            .then(function (response) { return response.json(); })
            .then(function (payload) {
                var count = payload.queue_count || 0;
                badge.textContent = 'Queue ' + count;
                if (count > lastQueue) beep();
                lastQueue = count;
                paintList('live-chat-queue', payload.queue || [], function (row) {
                    post(@json(url('/admin/live-chat')) + '/' + row.id + '/claim').then(function (body) {
                        current = row.id;
                        paintTranscript(body);
                        refresh();
                    });
                });
                paintList('live-chat-active', payload.active || [], function (row) { openTranscript(row.id); });
                if (current) openTranscript(current);
            }).catch(function () {});
    }

    presence.addEventListener('click', function () {
        var next = presence.getAttribute('data-status') === 'online' ? 'away' : 'online';
        post(@json(route('admin.live-chat.presence')), { status: next }).then(function (body) {
            presence.setAttribute('data-status', body.status);
            presence.textContent = body.status === 'online' ? 'You are online' : 'You are away';
        });
    });

    document.getElementById('live-chat-reply').addEventListener('submit', function (event) {
        event.preventDefault();
        if (!current) return;
        var field = document.getElementById('live-chat-body');
        post(@json(url('/admin/live-chat')) + '/' + current + '/reply', { body: field.value }).then(function (body) {
            field.value = '';
            paintTranscript(body);
        });
    });
    document.getElementById('live-chat-handback').addEventListener('click', function () {
        if (!current) return;
        post(@json(url('/admin/live-chat')) + '/' + current + '/hand-back').then(paintTranscript);
    });
    document.getElementById('live-chat-close').addEventListener('click', function () {
        if (!current) return;
        post(@json(url('/admin/live-chat')) + '/' + current + '/close').then(paintTranscript);
    });

    refresh();
    window.setInterval(refresh, 4000);
})();
</script>
@endpush
