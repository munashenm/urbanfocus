@php
    $chatAvailability = app(\App\Services\Chat\ChatAvailability::class)->snapshot();
@endphp
<div id="uf-chat" class="uf-chat" data-open="{{ $chatAvailability['open'] ? '1' : '0' }}"
    data-session-url="{{ route('chat.session') }}"
    data-message-url="{{ route('chat.messages') }}"
    data-handover-url="{{ route('chat.handover') }}"
    data-track-url="{{ route('chat.track') }}"
    data-request-url="{{ route('chat.requests') }}">
    <button type="button" class="uf-chat-launcher" aria-controls="uf-chat-panel" aria-expanded="false">
        <span class="uf-chat-launcher-label">Chat</span>
    </button>
    <section id="uf-chat-panel" class="uf-chat-panel" role="dialog" aria-modal="true" aria-labelledby="uf-chat-title" hidden>
        <header class="uf-chat-header">
            <div>
                <h2 id="uf-chat-title">Urban Focus</h2>
                <p class="uf-chat-availability" data-chat-availability>
                    <strong>{{ $chatAvailability['label'] }}.</strong> {{ $chatAvailability['message'] }}
                </p>
            </div>
            <button type="button" class="uf-chat-close" aria-label="Close chat">&times;</button>
        </header>

        <nav class="uf-chat-quick" aria-label="Quick help">
            <a data-uf-quick="search" href="{{ route('shop.index') }}">Search products</a>
            <a data-uf-quick="quote" href="{{ route('b2b.quote') }}">Request a quote</a>
            <a data-uf-quick="track" href="{{ route('orders.track') }}">Track an order</a>
            <a data-uf-quick="contact" href="{{ route('contact') }}">Contact</a>
        </nav>

        <div class="uf-chat-direct">
            <span>Or contact us directly:</span>
            <a href="tel:{{ $chatAvailability['phone_tel'] }}">{{ $chatAvailability['phone'] }}</a>
            <a href="mailto:{{ $chatAvailability['email'] }}">{{ $chatAvailability['email'] }}</a>
        </div>

        <div class="uf-chat-tools">
            <button type="button" data-chat-view="messages">Messages</button>
            <button type="button" data-chat-view="track">Track</button>
            <button type="button" data-chat-view="return">Returns</button>
            <button type="button" data-chat-view="warranty">Warranty</button>
            <button type="button" data-chat-view="support">Support</button>
            <button type="button" data-chat-handover>Talk to the team</button>
        </div>

        <div class="uf-chat-log" data-chat-log role="log" aria-live="polite" aria-relevant="additions"></div>

        <form class="uf-chat-compose" data-chat-compose>
            <label class="visually-hidden" for="uf-chat-body">Message</label>
            <textarea id="uf-chat-body" name="body" rows="2" maxlength="2000" required placeholder="Ask about a product, or leave a message for the team"></textarea>
            <button type="submit">Send</button>
        </form>

        <form class="uf-chat-form" data-chat-form="track" hidden>
            <p>Enter the order number and the email used at checkout. Both are required.</p>
            <label>Order number <input name="order_number" required maxlength="50" autocomplete="off"></label>
            <label>Checkout email <input name="email" type="email" required maxlength="255" autocomplete="email"></label>
            <button type="submit">Check order</button>
        </form>

        <form class="uf-chat-form" data-chat-form="return" hidden>
            <p>A return request is sent to the team for review. It is not approved from this chat. <a href="{{ route('returns') }}">Read the returns policy</a>.</p>
            <label>Name <input name="name" required maxlength="120" autocomplete="name"></label>
            <label>Email <input name="email" type="email" required maxlength="255" autocomplete="email"></label>
            <label>Phone <input name="phone" maxlength="40" autocomplete="tel"></label>
            <label>Order number <input name="order_number" required maxlength="50"></label>
            <label>Condition
                <select name="condition">
                    <option value="unused">Unused, in original packaging</option>
                    <option value="opened">Opened</option>
                    <option value="damaged">Damaged on arrival</option>
                    <option value="not_sure">Not sure</option>
                </select>
            </label>
            <label>What happened <textarea name="details" required maxlength="2000"></textarea></label>
            <button type="submit">Send return request</button>
        </form>

        <form class="uf-chat-form" data-chat-form="warranty" hidden>
            <p>A warranty request is sent to the team for review. It is not approved from this chat. Do not send the item until the team gives you a return authorisation reference. <a href="{{ route('warranty') }}">Read the warranty terms</a>.</p>
            <label>Name <input name="name" required maxlength="120" autocomplete="name"></label>
            <label>Email <input name="email" type="email" required maxlength="255" autocomplete="email"></label>
            <label>Phone <input name="phone" maxlength="40" autocomplete="tel"></label>
            <label>Order number <input name="order_number" required maxlength="50"></label>
            <label>Product <input name="product" required maxlength="200"></label>
            <label>Serial number <input name="serial_number" required maxlength="120"></label>
            <label>Fault <textarea name="details" required maxlength="2000"></textarea></label>
            <button type="submit">Send warranty request</button>
        </form>

        <form class="uf-chat-form" data-chat-form="support" hidden>
            <p>A person on the team reads support messages during published hours. This chat cannot approve a refund. <a href="{{ route('contact') }}">Contact page</a>.</p>
            <label>Name <input name="name" required maxlength="120" autocomplete="name"></label>
            <label>Email <input name="email" type="email" required maxlength="255" autocomplete="email"></label>
            <label>Phone <input name="phone" maxlength="40" autocomplete="tel"></label>
            <label>Order number <input name="order_number" maxlength="50"></label>
            <label>How can we help <textarea name="details" required maxlength="2000"></textarea></label>
            <button type="submit">Send to the team</button>
        </form>
    </section>
</div>
@push('scripts')
    <script src="{{ asset('js/chat.js') }}" defer></script>
@endpush
