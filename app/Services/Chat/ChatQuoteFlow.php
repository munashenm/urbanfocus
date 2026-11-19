<?php

namespace App\Services\Chat;

use App\Mail\NewQuoteNotification;
use App\Models\ChatConversation;
use App\Models\Product;
use App\Models\Quote;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Collects a quote in the chat and stores it as a normal B2B quote.
 * Prices are never quoted and nothing is approved.
 */
class ChatQuoteFlow
{
    public function handle(ChatConversation $conversation, string $body): ?string
    {
        $body = trim($body);
        $draft = $conversation->quote_draft;
        $active = is_array($draft) && ($draft['active'] ?? false) === true;

        if ($active && preg_match('/\b(cancel|stop)\b/i', $body)) {
            $conversation->forceFill(['quote_draft' => null, 'topic' => 'general'])->save();

            return 'The quote request is cancelled. Nothing was submitted and no price was quoted.';
        }

        if (! $active && ! $this->wantsQuote($body)) {
            return null;
        }

        if (! $active) {
            $draft = $this->absorb($this->blank(), $body);
        } else {
            if ($this->isPriceQuestion($body) && ($draft['awaiting'] ?? '') !== 'confirm') {
                $conversation->forceFill(['quote_draft' => $draft, 'topic' => 'quote'])->save();

                return "I can't give a price, a discount, or an approval in this chat. ".$this->prompt($draft);
            }

            if (($draft['awaiting'] ?? '') === 'confirm') {
                if ($this->isYes($body)) {
                    return $this->submit($conversation, $draft);
                }

                return "Reply yes to send this quote request to the team, or cancel to stop. No price is quoted and nothing is approved.\n\n".$this->summary($draft);
            }

            $draft = $this->fillAwaiting($draft, $body);
        }

        $draft['awaiting'] = $this->nextMissing($draft);
        $draft['active'] = true;
        $conversation->forceFill(['quote_draft' => $draft, 'topic' => 'quote'])->save();
        $this->rememberVisitor($conversation, $draft);

        if ($draft['awaiting'] === 'confirm') {
            return $this->summary($draft)."\n\nReply yes to send this to the Urban Focus team. No price is quoted and nothing is approved.";
        }

        return $this->prompt($draft);
    }

    public function wantsQuote(string $body): bool
    {
        return (bool) preg_match('/\b(?:please\s+)?(?:request|get|need|want|send|prepare|make)\s+(?:me\s+)?(?:a\s+)?(?:formal\s+)?(?:quote|quotation|rfq)\b|\bquote for\b|\brfq for\b|\bplease quote\b/i', $body);
    }

    /**
     * @return array<string, mixed>
     */
    protected function blank(): array
    {
        return [
            'active' => true,
            'items' => [],
            'company' => null,
            'name' => null,
            'email' => null,
            'phone' => null,
            'phone_skipped' => false,
            'area' => null,
            'notes' => null,
            'notes_skipped' => false,
            'awaiting' => 'items',
        ];
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function absorb(array $draft, string $body): array
    {
        $items = $this->parseItems($body);
        if ($items !== []) {
            $draft['items'] = $items;
        }

        if (preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $body, $email)) {
            $draft['email'] = strtolower($email[0]);
        }

        if (preg_match('/(?:\+27|0)\d[\d\s\-()]{7,}\d/', $body, $phone)) {
            $draft['phone'] = trim($phone[0]);
        }

        if (preg_match('/\bcompany(?:\s+name)?\s*(?:is|:)\s*([^\n,]{2,150})/i', $body, $company)) {
            $draft['company'] = trim($company[1]);
        }

        if (preg_match('/\b(?:my name is|i am|name\s*:)\s*([A-Za-z][A-Za-z \'-]{1,80})/i', $body, $name)) {
            $draft['name'] = trim($name[1]);
        }

        if (preg_match('/\b(?:deliver(?:y)?(?:\s+area)?|ship to)\s*(?:is|:)?\s*([A-Za-z][A-Za-z \'-]{2,80})/i', $body, $area)) {
            $draft['area'] = trim($area[1]);
        }

        if (preg_match('/\bnotes?\s*:\s*(.+)/i', $body, $notes)) {
            $draft['notes'] = trim($notes[1]);
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $draft
     * @return array<string, mixed>
     */
    protected function fillAwaiting(array $draft, string $body): array
    {
        $value = trim($body);

        switch ($draft['awaiting'] ?? 'items') {
            case 'items':
                $parsed = $this->parseItems($value);
                $draft['items'] = $parsed !== [] ? $parsed : [[
                    'label' => $value,
                    'quantity' => 1,
                    'sku' => null,
                    'product_id' => null,
                ]];
                break;
            case 'company':
                $draft['company'] = $value;
                break;
            case 'name':
                $draft['name'] = $value;
                break;
            case 'email':
                $draft['email'] = filter_var($value, FILTER_VALIDATE_EMAIL) ? strtolower($value) : null;
                break;
            case 'phone':
                if ($this->isSkip($value)) {
                    $draft['phone_skipped'] = true;
                    $draft['phone'] = null;
                } else {
                    $draft['phone'] = $value;
                }
                break;
            case 'area':
                $draft['area'] = $value;
                break;
            case 'notes':
                if ($this->isSkip($value)) {
                    $draft['notes_skipped'] = true;
                    $draft['notes'] = null;
                } else {
                    $draft['notes'] = $value;
                }
                break;
        }

        return $draft;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function nextMissing(array $draft): string
    {
        if ($draft['items'] === []) {
            return 'items';
        }
        if (blank($draft['company'] ?? null)) {
            return 'company';
        }
        if (blank($draft['name'] ?? null)) {
            return 'name';
        }
        if (! filter_var((string) ($draft['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            return 'email';
        }
        if (blank($draft['phone'] ?? null) && empty($draft['phone_skipped'])) {
            return 'phone';
        }
        if (blank($draft['area'] ?? null)) {
            return 'area';
        }
        if (($draft['notes'] ?? null) === null && empty($draft['notes_skipped'])) {
            return 'notes';
        }

        return 'confirm';
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function prompt(array $draft): string
    {
        return match ($draft['awaiting'] ?? 'items') {
            'company' => 'What is the company name?',
            'name' => 'What is the contact name?',
            'email' => 'What email address should the team use for this quote?',
            'phone' => 'What phone number should we use? Reply skip if you do not want to give one.',
            'area' => 'Which town or area should delivery be quoted for?',
            'notes' => 'Any notes for the team? Reply skip if there are none.',
            'confirm' => 'Reply yes to send this quote request. No price is quoted and nothing is approved.',
            default => 'What products or SKUs do you need, and how many of each? For example: 4 x USW-ULTRA. I will not quote a price in this chat.',
        };
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function summary(array $draft): string
    {
        $lines = ['Quote request summary (no prices):'];
        foreach ($draft['items'] as $item) {
            $line = $item['quantity'].' × '.$item['label'];
            if (! empty($item['sku'])) {
                $line .= ' (SKU '.$item['sku'].')';
            }
            $lines[] = '- '.$line;
        }
        $lines[] = 'Company: '.$draft['company'];
        $lines[] = 'Contact: '.$draft['name'];
        $lines[] = 'Email: '.$draft['email'];
        $lines[] = 'Phone: '.($draft['phone'] ?: 'not given');
        $lines[] = 'Delivery area: '.$draft['area'];
        $lines[] = 'Notes: '.($draft['notes'] ?: 'none');

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function submit(ChatConversation $conversation, array $draft): string
    {
        $productId = null;
        foreach ($draft['items'] as $item) {
            if (! empty($item['product_id'])) {
                $productId = $item['product_id'];
                break;
            }
        }

        $quote = Quote::query()->create([
            'type' => 'quote',
            'name' => $draft['name'],
            'company' => $draft['company'],
            'email' => $draft['email'],
            'phone' => $draft['phone'] ?: null,
            'message' => "Submitted from customer chat. No price is quoted and nothing is approved.\n\n".$this->summary($draft),
            'product_id' => $productId,
        ]);
        $quote->load('product');

        $email = config('app.email') ?: config('business.email');
        if (is_string($email) && $email !== '') {
            try {
                Mail::to($email)->send(new NewQuoteNotification($quote));
            } catch (Throwable $e) {
                Log::warning('Chat quote notification failed', ['error' => $e->getMessage()]);
            }
        }

        $conversation->forceFill([
            'quote_draft' => null,
            'topic' => 'quote',
            'visitor_name' => $draft['name'],
            'visitor_email' => $draft['email'],
            'visitor_phone' => $draft['phone'] ?: $conversation->visitor_phone,
        ])->save();

        return 'Your quote request has been sent to the Urban Focus team (reference '.$quote->id.'). '
            .'No price has been quoted and nothing is approved. '
            .'The team will reply by email during published hours ('.config('business.hours').').';
    }

    /**
     * @return list<array{label: string, quantity: int, sku: ?string, product_id: ?int}>
     */
    protected function parseItems(string $body): array
    {
        preg_match_all('/(\d+)\s*(?:x|×)\s*([A-Za-z0-9][A-Za-z0-9\-]*)/i', $body, $matches, PREG_SET_ORDER);
        $items = [];

        foreach ($matches as $match) {
            $token = $match[2];
            $product = Product::query()
                ->where('is_active', true)
                ->whereRaw('LOWER(sku) = ?', [mb_strtolower($token)])
                ->first();

            $items[] = [
                'label' => $product?->name ?: $token,
                'quantity' => max(1, (int) $match[1]),
                'sku' => $product?->sku ?: strtoupper($token),
                'product_id' => $product?->id,
            ];
        }

        return $items;
    }

    /**
     * @param  array<string, mixed>  $draft
     */
    protected function rememberVisitor(ChatConversation $conversation, array $draft): void
    {
        $updates = [];
        if (filled($draft['name'] ?? null)) {
            $updates['visitor_name'] = $draft['name'];
        }
        if (filter_var((string) ($draft['email'] ?? ''), FILTER_VALIDATE_EMAIL)) {
            $updates['visitor_email'] = $draft['email'];
        }
        if (filled($draft['phone'] ?? null)) {
            $updates['visitor_phone'] = $draft['phone'];
        }
        if ($updates !== []) {
            $conversation->fill($updates)->save();
        }
    }

    protected function isSkip(string $body): bool
    {
        return (bool) preg_match('/^(skip|no|none|no thanks|n\/a)$/i', trim($body));
    }

    protected function isYes(string $body): bool
    {
        return (bool) preg_match('/^(yes|y|submit|confirm)$/i', trim($body));
    }

    protected function isPriceQuestion(string $body): bool
    {
        return (bool) preg_match('/\b(how much|price|prices|discount)\b/i', $body);
    }
}
