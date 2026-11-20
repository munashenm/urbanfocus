@php
    $chatAvailability = app(\App\Services\Chat\ChatAvailability::class)->snapshot();
    $chatTrouble = 'Sorry, chat is having trouble. Email '.$chatAvailability['email'].' or call '.$chatAvailability['phone'].'.';
@endphp
<div id="uf-chat" class="uf-chat" data-open="{{ $chatAvailability['open'] ? '1' : '0' }}"
    data-session-url="{{ route('chat.session') }}"
    data-message-url="{{ route('chat.messages') }}"
    data-handover-url="{{ route('chat.handover') }}"
    data-track-url="{{ route('chat.track') }}"
    data-request-url="{{ route('chat.requests') }}"
    data-trouble="{{ $chatTrouble }}">
    <button type="button" class="uf-chat-launcher" aria-controls="uf-chat-panel" aria-expanded="false">
        <svg class="uf-chat-launcher-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
            <path fill="currentColor" d="M5 4h14a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H9l-4 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/>
        </svg>
        <span class="uf-chat-launcher-label">Chat</span>
    </button>
    <section id="uf-chat-panel" class="uf-chat-panel" role="dialog" aria-modal="true" aria-labelledby="uf-chat-title" hidden>
        <header class="uf-chat-header">
            <div>
                <h2 id="uf-chat-title">Urban Focus</h2>
                <p class="uf-chat-availability" data-chat-availability>{{ $chatAvailability['message'] }}</p>
            </div>
            <div class="uf-chat-header-actions">
                <button type="button" class="uf-chat-min" aria-label="Minimise chat">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path fill="currentColor" d="M5 11h14v2H5z"/></svg>
                </button>
                <button type="button" class="uf-chat-close" aria-label="Close chat">&times;</button>
            </div>
        </header>

        <div class="uf-chat-body">
            <div class="uf-chat-tools" data-chat-tools>
                <nav class="uf-chat-quick" aria-label="Quick help">
                    <a data-uf-quick="search" href="{{ route('shop.index') }}">Search products</a>
                    <a data-uf-quick="quote" href="{{ route('b2b.quote') }}">Request a quote</a>
                    <button type="button" data-uf-quick="track" data-chat-view="track">Track an order</button>
                    <button type="button" data-chat-handover>Talk to the team</button>
                </nav>

                <div class="uf-chat-direct">
                    <a href="tel:{{ $chatAvailability['phone_tel'] }}">{{ $chatAvailability['phone'] }}</a>
                    <a href="mailto:{{ $chatAvailability['email'] }}">{{ $chatAvailability['email'] }}</a>
                    <button type="button" data-chat-view="return">Returns</button>
                    <button type="button" data-chat-view="warranty">Warranty</button>
                </div>
            </div>

            <div class="uf-chat-log" data-chat-log role="log" aria-live="polite" aria-relevant="additions">
                <p class="uf-chat-greeting">Hi! Looking for a product or a quote? Ask away.</p>
            </div>

            <form class="uf-chat-compose" data-chat-compose>
                <div class="uf-chat-contact" data-chat-contact @if($chatAvailability['open']) hidden @endif>
                    <label>Name <input name="name" maxlength="120" autocomplete="name" @unless($chatAvailability['open']) required @endunless></label>
                    <label>Email <input name="email" type="email" maxlength="255" autocomplete="email" @unless($chatAvailability['open']) required @endunless></label>
                    <label>Phone <input name="phone" maxlength="40" autocomplete="tel"></label>
                </div>
                <div class="uf-chat-compose-row">
                    <label class="visually-hidden" for="uf-chat-body">Message</label>
                    <textarea id="uf-chat-body" name="body" rows="2" maxlength="2000" required placeholder="Ask about a product, or leave a message"></textarea>
                    <button type="submit">Send</button>
                </div>
            </form>

            <form class="uf-chat-form" data-chat-form="track" hidden>
                <p>Enter the order number and the email used at checkout. Both are required. <a href="{{ route('orders.track') }}">Open the tracking page</a>.</p>
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
        </div>
    </section>
</div>
@push('scripts')
    <script src="{{ asset('js/chat.js') }}" defer></script>
@endpush
