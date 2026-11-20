<?php

namespace App\Services\Chat;

use App\Mail\ChatHandoverAlert;
use App\Mail\ChatStaffAlert;
use App\Models\ChatConversation;
use App\Models\ChatInsight;
use App\Models\ChatMessage;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class ChatService
{
    public function __construct(
        protected ChatAvailability $availability,
        protected ChatPolicyGuide $policies,
        protected CatalogueFacts $catalogue,
        protected ChatAssistant $assistant,
        protected ChatScope $scope,
        protected BusinessKnowledge $knowledge,
        protected ChatQuoteFlow $quotes,
        protected ChatSmsAlert $sms,
        protected ChatLiveService $live,
    ) {}

    public function current(): ?ChatConversation
    {
        $token = session('chat_public_token');
        if (! is_string($token) || $token === '') {
            return null;
        }

        return ChatConversation::query()->where('public_token', $token)->first();
    }

    public function conversation(): ChatConversation
    {
        $existing = $this->current();
        if ($existing) {
            return $existing;
        }

        $conversation = ChatConversation::create([
            'public_token' => (string) Str::uuid(),
            'status' => 'open',
            'topic' => 'general',
        ]);

        session(['chat_public_token' => $conversation->public_token]);

        return $conversation;
    }

    public function handleMessage(string $body, ?string $intent = null, ?string $pageUrl = null, ?string $name = null, ?string $email = null, ?string $phone = null): ChatConversation
    {
        $body = trim(strip_tags($body));
        $this->guardOfflineContact($name, $email);
        $conversation = $this->conversation();
        $this->rememberVisitor($conversation, $name, $email, $phone);
        $this->rememberPage($conversation, $pageUrl);
        $this->reopen($conversation);

        $visitor = $this->add($conversation, 'visitor', 'message', $body);
        $this->acknowledgeOffline($conversation);

        if ($this->isHandover($body, $intent, $conversation)) {
            $this->alertHandoverOnce($conversation);
            $this->markWaiting($conversation, 'general');
            $this->add($conversation, 'assistant', 'handover', $this->handoverCopy());
            $this->live->notifyQueued($conversation->fresh());

            return $conversation->fresh();
        }

        if ($conversation->bot_paused) {
            if ($conversation->status === 'live') {
                $this->live->notifyAssigned($conversation, $visitor);
            } else {
                $this->live->notifyQueued($conversation);
            }

            return $conversation->fresh();
        }

        $quoteReply = $this->quotes->handle($conversation, $body);
        if ($quoteReply !== null) {
            $this->add($conversation, 'assistant', 'message', $quoteReply);
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        $tracked = $this->trackingPair($body);
        if ($tracked !== null) {
            $this->recordTracking($conversation, $tracked['order_number'], $tracked['email'], $visitor);

            return $conversation->fresh();
        }

        if ($this->asksForTracking($body)) {
            $conversation->update(['topic' => 'tracking']);
            $this->add($conversation, 'assistant', 'tracking', $this->trackingPrompt());
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        if ($this->isReturnQuestion($body)) {
            $conversation->update(['topic' => 'return']);
            $this->add($conversation, 'assistant', 'message', $this->policies->returnsSummary()."\nUse the Returns form in this chat to send the request to the team.");
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        if ($this->isWarrantyQuestion($body)) {
            $facts = $this->scope->isProduct($body) ? $this->catalogue->forQuestion($body) : [];
            $conversation->update(['topic' => 'warranty']);
            $text = $this->policies->warrantySummary();
            if ($facts !== []) {
                $text .= "\n\n".$this->assistant->template($facts);
            }
            $text .= "\nUse the Warranty form in this chat to send a claim to the team. A claim is not approved from this chat.";
            $this->add($conversation, 'assistant', 'message', $text, $facts === [] ? [] : $this->assistant->cards($facts));
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        $classification = $this->scope->classify($body);

        if ($classification === ChatScope::CLARIFY) {
            $this->add($conversation, 'assistant', 'message', $this->scope->clarify());
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        if ($classification === ChatScope::OFF_TOPIC) {
            $this->add($conversation, 'assistant', 'message', $this->scope->refusal());
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        if ($classification === ChatScope::GREETING) {
            $this->add($conversation, 'assistant', 'message', $this->scope->greeting());
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        if ($classification === ChatScope::BUSINESS) {
            $answer = $this->assistant->answerBusiness($this->redact($body), $this->knowledge->forQuestion($body));
            $conversation->update(['topic' => 'general']);
            $this->add($conversation, 'assistant', 'message', $answer['body'], []);
            $this->logIfUnanswered($conversation, $body, $answer['body']);
            $this->notifyIfOffline($conversation, $visitor);

            return $conversation->fresh();
        }

        $facts = $this->catalogue->forQuestion($body);
        if ($facts === []) {
            ChatInsight::record($conversation, 'not_found', $this->redact($body));
        }
        $answer = $this->assistant->answer($this->redact($body), $facts);
        $conversation->update(['topic' => $facts === [] ? 'general' : 'product']);
        $this->add($conversation, 'assistant', 'message', $answer['body'], $answer['cards']);
        $this->notifyIfOffline($conversation, $visitor);

        return $conversation->fresh();
    }

    public function handover(?string $pageUrl = null, ?string $name = null, ?string $email = null, ?string $phone = null): ChatConversation
    {
        $this->guardOfflineContact($name, $email);
        $conversation = $this->conversation();
        $this->rememberVisitor($conversation, $name, $email, $phone);
        $this->rememberPage($conversation, $pageUrl);
        $this->reopen($conversation);
        $this->add($conversation, 'visitor', 'handover', 'The customer asked to talk to the team.');
        $this->acknowledgeOffline($conversation);
        $this->alertHandoverOnce($conversation);
        $this->markWaiting($conversation, $conversation->topic ?: 'general');
        $this->add($conversation, 'assistant', 'handover', $this->handoverCopy());
        $this->live->notifyQueued($conversation->fresh());

        return $conversation->fresh();
    }

    public function track(string $orderNumber, string $email): ChatConversation
    {
        $conversation = $this->conversation();
        $this->reopen($conversation);
        $orderNumber = strtoupper(trim($orderNumber));
        $email = strtolower(trim($email));

        $visitor = $this->add($conversation, 'visitor', 'tracking', 'Asked to track order '.$orderNumber.'.', payload: [
            'order_number' => $orderNumber,
            'email' => $email,
        ]);
        $this->recordTracking($conversation, $orderNumber, $email, $visitor);

        return $conversation->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function request(array $input): ChatConversation
    {
        $type = (string) $input['type'];
        $conversation = $this->conversation();
        $this->rememberPage($conversation, $input['page_url'] ?? null);
        $this->reopen($conversation);

        $payload = [
            'type' => $type,
            'name' => $input['name'],
            'email' => $input['email'],
            'phone' => $input['phone'] ?? null,
            'order_number' => isset($input['order_number']) ? strtoupper(trim((string) $input['order_number'])) : null,
            'product' => $input['product'] ?? null,
            'serial_number' => $input['serial_number'] ?? null,
            'condition' => $input['condition'] ?? null,
        ];

        $conversation->fill([
            'visitor_name' => $input['name'],
            'visitor_email' => $input['email'],
            'visitor_phone' => $input['phone'] ?? $conversation->visitor_phone,
            'topic' => $type,
            'status' => 'waiting_staff',
        ])->save();

        $kind = $type.'_request';
        $visitor = $this->add(
            $conversation,
            'visitor',
            $kind,
            trim((string) $input['details']),
            payload: $payload,
            reviewStatus: 'submitted',
        );

        $summary = match ($type) {
            'return' => $this->policies->returnsSummary(),
            'warranty' => $this->policies->warrantySummary(),
            default => $this->policies->supportSummary(),
        };

        $confirmation = match ($type) {
            'return' => 'We have sent your return request to the Urban Focus team. It is not approved. A person will check it against the returns policy and reply during published hours.',
            'warranty' => 'We have sent your warranty request to the Urban Focus team. It is not approved. A person will check the product record and the warranty terms before any claim can proceed. Do not send the item until the team gives you a return authorisation reference.',
            default => 'We have sent your message to the Urban Focus team. A person will read it during published hours. This chat has not closed the request or approved a refund.',
        };

        $this->add($conversation, 'assistant', $kind, $confirmation."\n\n".$summary);
        $this->notifyStaff($conversation, $visitor);

        return $conversation->fresh();
    }

    public function staffReply(ChatConversation $conversation, string $body, int $userId): ChatMessage
    {
        if ($conversation->status === 'closed') {
            $conversation->status = 'open';
        }

        $message = $this->add($conversation, 'staff', 'message', trim(strip_tags($body)), userId: $userId);
        $updates = [
            'staff_read_at' => now(),
            'status' => $conversation->status === 'closed' ? 'open' : $conversation->status,
        ];
        if ($conversation->status === 'waiting_staff' && $conversation->assigned_user_id === null) {
            $updates['assigned_user_id'] = $userId;
            $updates['claimed_at'] = now();
            $updates['status'] = 'live';
            $updates['bot_paused'] = true;
        }
        $conversation->forceFill($updates)->save();

        return $message;
    }

    public function redact(string $text): string
    {
        $text = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email]', $text) ?? $text;
        $text = preg_replace('/\bUF-[A-Z0-9]+\b/i', '[order]', $text) ?? $text;
        $text = preg_replace('/(?:\+27|0)\d[\d\s\-()]{7,}\d/', '[phone]', $text) ?? $text;
        $text = preg_replace('/\b\d{1,5}\s+[A-Za-z0-9.\'\s]{0,40}?\b(?:street|st|road|rd|avenue|ave|drive|dr|lane|ln)\b/i', '[address]', $text) ?? $text;

        return trim($text);
    }

    /**
     * @param  list<array<string, string>>|null  $cards
     * @param  array<string, mixed>|null  $payload
     */
    protected function add(
        ChatConversation $conversation,
        string $sender,
        string $kind,
        string $body,
        ?array $cards = null,
        ?array $payload = null,
        ?string $reviewStatus = null,
        ?int $userId = null,
    ): ChatMessage {
        $message = $conversation->messages()->create([
            'sender' => $sender,
            'user_id' => $userId,
            'kind' => $kind,
            'body' => $body,
            'cards' => $cards,
            'payload' => $payload,
            'review_status' => $reviewStatus,
        ]);

        $conversation->forceFill(['last_message_at' => $message->created_at ?? now()])->save();

        return $message;
    }

    protected function recordTracking(ChatConversation $conversation, string $orderNumber, string $email, ChatMessage $visitor): void
    {
        $conversation->update(['topic' => 'tracking']);

        $order = Order::query()
            ->where('order_number', strtoupper(trim($orderNumber)))
            ->whereRaw('LOWER(customer_email) = ?', [strtolower(trim($email))])
            ->first();

        if (! $order) {
            $this->add($conversation, 'assistant', 'tracking', 'No order found with those details. Check the order number and the email used at checkout. Both are required, and a mismatch is not described separately.');
            $this->notifyIfOffline($conversation, $visitor);

            return;
        }

        $order->load('items');
        $lines = $order->items->map(fn ($item) => $item->quantity.' × '.$item->product_name)->implode('; ');
        $text = 'Order '.$order->order_number.' is recorded as '.str_replace('_', ' ', $order->status).'. '
            .'Payment is recorded as '.str_replace('_', ' ', (string) $order->payment_status).'.';
        if ($lines !== '') {
            $text .= ' Items: '.$lines.'.';
        }
        $text .= ' Order total: R '.number_format((float) $order->total, 2).'.';
        if ($order->shipped_at) {
            $text .= ' The order record says it was shipped on '.$order->shipped_at->timezone(config('chat.timezone'))->format('j M Y').'.';
        }
        $text .= ' This is the status stored on the order. It is not a new delivery date.';

        $this->add($conversation, 'assistant', 'tracking', $text);
    }

    /**
     * @return array{order_number: string, email: string}|null
     */
    protected function trackingPair(string $body): ?array
    {
        if (! preg_match('/\b(UF-[A-Z0-9]+)\b/i', $body, $order)) {
            return null;
        }
        if (! preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $body, $email)) {
            return null;
        }

        return [
            'order_number' => strtoupper($order[1]),
            'email' => strtolower($email[0]),
        ];
    }

    protected function asksForTracking(string $body): bool
    {
        return (bool) preg_match('/\b(track(ing)?|where is my order|order status)\b/i', $body);
    }

    protected function isHandover(string $body, ?string $intent, ?ChatConversation $conversation = null): bool
    {
        if ($intent === 'handover') {
            return true;
        }

        if (preg_match('/\b(talk|speak|chat)\s+to\s+(a\s+)?(person|human|someone|staff|the team|agent)\b|\b(real person|live agent|live chat)\b/i', $body)) {
            return true;
        }

        if ($conversation && preg_match('/^(yes|yeah|yep|please|ok|okay|connect me)[.!\s]*$/iu', trim($body))) {
            $last = $conversation->messages()->where('sender', 'assistant')->orderByDesc('id')->value('body');

            return is_string($last) && str_contains(strtolower($last), 'live agent');
        }

        return false;
    }

    protected function isReturnQuestion(string $body): bool
    {
        return (bool) preg_match('/\b(return|refund|exchange)\b/i', $body);
    }

    protected function isWarrantyQuestion(string $body): bool
    {
        return (bool) preg_match('/\b(warranty|guarantee|doa)\b/i', $body);
    }

    protected function handoverCopy(): string
    {
        $availability = $this->availability->snapshot();
        $contact = 'You can also call '.$availability['phone'].' or email '.$availability['email'].'.';

        if ($availability['open']) {
            return 'A person on the Urban Focus team has this conversation. You are in the queue, and the first available agent will join this chat. We are open until '
                .$availability['closes'].' today ('.$availability['hours'].'). '
                .'A reply will come during these hours, not necessarily straight away. '.$contact;
        }

        return 'A person on the Urban Focus team has this conversation. We are offline until '
            .$availability['next_open'].' ('.$availability['hours'].'). '
            .'They will read it when we are back. This is not an immediate reply. '.$contact;
    }

    protected function alertHandoverOnce(ChatConversation $conversation): void
    {
        $alreadyAlerted = $conversation->status === 'waiting_staff' && $conversation->handover_alerted_at !== null;
        if ($alreadyAlerted) {
            return;
        }

        $recent = $conversation->messages()->orderByDesc('id')->limit(8)->get()->reverse()->values();
        $email = config('business.email');
        if (is_string($email) && $email !== '') {
            try {
                Mail::to($email)->send(new ChatHandoverAlert($conversation, $recent));
            } catch (Throwable $e) {
                Log::warning('Customer chat handover alert failed', ['error' => $e->getMessage()]);
            }
        }

        $this->sms->send($conversation);
        $conversation->forceFill(['handover_alerted_at' => now()])->save();
    }

    protected function logIfUnanswered(ChatConversation $conversation, string $question, string $reply): void
    {
        if (preg_match('/do not have that detail|don\'t have that detail/i', $reply)) {
            ChatInsight::record($conversation, 'unanswered', $this->redact($question));
        }
    }

    protected function guardOfflineContact(?string $name, ?string $email): void
    {
        if ($this->availability->isOpen()) {
            return;
        }

        $existing = $this->current();
        $name = trim((string) ($name ?: $existing?->visitor_name));
        $email = trim((string) ($email ?: $existing?->visitor_email));

        if ($name === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'email' => 'Please leave your name and email so we can reply.',
            ]);
        }
    }

    protected function rememberVisitor(ChatConversation $conversation, ?string $name, ?string $email, ?string $phone = null): void
    {
        $updates = [];
        $name = trim((string) $name);
        $email = strtolower(trim((string) $email));
        $phone = trim(strip_tags((string) $phone));

        if ($name !== '') {
            $updates['visitor_name'] = $name;
        }
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $updates['visitor_email'] = $email;
        }
        if ($phone !== '') {
            $updates['visitor_phone'] = mb_substr($phone, 0, 40);
        }

        if ($updates !== []) {
            $conversation->fill($updates)->save();
        }
    }

    protected function acknowledgeOffline(ChatConversation $conversation): void
    {
        if ($this->availability->isOpen() || blank($conversation->visitor_email)) {
            return;
        }

        if ($conversation->messages()->where('kind', 'offline_ack')->exists()) {
            return;
        }

        $this->add(
            $conversation,
            'assistant',
            'offline_ack',
            "Thanks, we'll get back to you by ".$this->availability->nextOpenLabel().'.',
        );
    }

    protected function trackingPrompt(): string
    {
        return 'To check an order I need both the order number and the email used at checkout. '
            .'Use the Track an order form in this chat. The order number alone is not enough, and the result stays in this chat only.';
    }

    protected function markWaiting(ChatConversation $conversation, string $topic): void
    {
        $conversation->forceFill([
            'status' => 'waiting_staff',
            'topic' => $topic,
            'bot_paused' => true,
            'assigned_user_id' => null,
        ])->save();
    }

    protected function reopen(ChatConversation $conversation): void
    {
        if ($conversation->status === 'closed') {
            $conversation->forceFill([
                'status' => 'open',
                'bot_paused' => false,
                'assigned_user_id' => null,
            ])->save();
        }
    }

    protected function rememberPage(ChatConversation $conversation, mixed $pageUrl): void
    {
        if (! is_string($pageUrl) || $pageUrl === '') {
            return;
        }

        $pageUrl = trim($pageUrl);
        if (strlen($pageUrl) > 500) {
            return;
        }

        $app = rtrim((string) config('app.url'), '/');
        $allowed = str_starts_with($pageUrl, '/')
            || ($app !== '' && str_starts_with($pageUrl, $app));

        if ($allowed && ! str_starts_with(strtolower($pageUrl), 'javascript:')) {
            $conversation->forceFill(['page_url' => $pageUrl])->save();
        }
    }

    protected function notifyIfOffline(ChatConversation $conversation, ChatMessage $message): void
    {
        if (! $this->availability->isOpen()) {
            $this->notifyStaff($conversation, $message);
        }
    }

    protected function notifyStaff(ChatConversation $conversation, ChatMessage $message): void
    {
        $email = config('business.email');
        if (! is_string($email) || $email === '') {
            return;
        }

        try {
            Mail::to($email)->send(new ChatStaffAlert($conversation, $message));
        } catch (Throwable $e) {
            Log::warning('Customer chat staff alert failed', ['error' => $e->getMessage()]);
        }
    }
}
