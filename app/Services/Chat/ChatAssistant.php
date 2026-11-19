<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ChatAssistant
{
    /**
     * @param  list<array<string, mixed>>  $facts
     * @return array{body: string, cards: list<array<string, mixed>>, source: string}
     */
    public function answer(string $question, array $facts): array
    {
        $cards = $this->cards($facts);

        if ($facts === []) {
            return [
                'body' => 'I could not find a matching product in the current Urban Focus catalogue. '
                    .'Try product search, or use Talk to the team and a person will check. '
                    .'I will not guess specifications, stock, a delivery date or a discount.',
                'cards' => [],
                'source' => 'catalogue',
            ];
        }

        $fallback = $this->template($facts);

        if (! $this->enabled()) {
            return ['body' => $fallback, 'cards' => $cards, 'source' => 'catalogue'];
        }

        $generated = $this->complete($question, $facts);
        if ($generated !== null && $this->isFaithful($generated, $facts)) {
            return ['body' => $generated, 'cards' => $cards, 'source' => 'openai'];
        }

        return ['body' => $fallback, 'cards' => $cards, 'source' => 'catalogue'];
    }

    public function enabled(): bool
    {
        return (bool) config('chat.openai.enabled') && filled(config('chat.openai.api_key'));
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     */
    public function isFaithful(string $reply, array $facts): bool
    {
        $reply = trim($reply);
        if ($reply === '') {
            return false;
        }

        if (preg_match('/\b(approved|refunded|refund has been|claim is accepted)\b/i', $reply)
            && ! preg_match('/\bnot approved\b/i', $reply)) {
            return false;
        }

        if (preg_match('/\b\d{1,2}\s+(January|February|March|April|May|June|July|August|September|October|November|December)\b/i', $reply)) {
            return false;
        }

        if (preg_match('/\b20\d{2}-\d{2}-\d{2}\b/', $reply)) {
            return false;
        }

        $allowedMoney = [];
        $allowedPercents = [];
        $anyAvailable = false;
        $allAvailable = $facts !== [];
        $skus = [];

        foreach ($facts as $fact) {
            foreach (['price', 'list_price'] as $key) {
                if (! empty($fact[$key])) {
                    $allowedMoney[] = $this->normalizeMoney((string) $fact[$key]);
                }
            }
            if ($fact['discount_percent'] !== null && $fact['discount_percent'] !== '') {
                $allowedPercents[] = (string) (int) $fact['discount_percent'];
            }
            $anyAvailable = $anyAvailable || ! empty($fact['available']);
            $allAvailable = $allAvailable && ! empty($fact['available']);
            if (! empty($fact['sku'])) {
                $skus[] = strtoupper((string) $fact['sku']);
            }
        }

        if (preg_match_all('/R\s*([0-9][0-9\s,]*(?:\.\d{1,2})?)/', $reply, $money)) {
            foreach ($money[1] as $amount) {
                if (! in_array($this->normalizeMoney($amount), $allowedMoney, true)) {
                    return false;
                }
            }
        }

        if (preg_match_all('/(\d+)\s*%/', $reply, $percents)) {
            foreach ($percents[1] as $percent) {
                if (! in_array($percent, $allowedPercents, true)) {
                    return false;
                }
            }
        }

        $mentionsDiscount = (bool) preg_match('/\b(discount|%\s*off|percent off)\b/i', $reply);
        if ($mentionsDiscount && $allowedPercents === []) {
            return false;
        }

        if (! $anyAvailable && preg_match('/(?<!not )(?<!no )\bin stock\b/i', $reply)) {
            return false;
        }

        if ($allAvailable && preg_match('/\bout of stock\b/i', $reply)) {
            return false;
        }

        if (preg_match_all('/\b[A-Z]{2,}[-][A-Z0-9-]{2,}\b/', $reply, $codes)) {
            foreach ($codes[0] as $code) {
                if (! in_array(strtoupper($code), $skus, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     */
    public function template(array $facts): string
    {
        $lines = ['Here is what the Urban Focus catalogue shows right now:'];

        foreach ($facts as $fact) {
            $lines[] = $fact['name'].' (SKU '.$fact['sku'].') — '.$fact['price_label'].' — '.$fact['availability'].'.';
            if (! empty($fact['list_price_label']) && $fact['discount_percent']) {
                $lines[] = 'The current catalogue price is '.$fact['price_label']
                    .' (regular '.$fact['list_price_label'].', '.$fact['discount_percent'].'% off).';
            }
            $lines[] = 'Warranty on the product record: '.$fact['warranty'].'.';
            $lines[] = 'Catalogue delivery guidance: '.$fact['delivery_guidance'].'. This is not a confirmed delivery date.';
            if (! empty($fact['specifications'])) {
                $bits = [];
                foreach ($fact['specifications'] as $key => $value) {
                    $bits[] = $key.': '.$value;
                }
                $lines[] = 'Specifications: '.implode('; ', $bits).'.';
            }
            $lines[] = $fact['url'];
        }

        $lines[] = 'I can only confirm the details above. For anything else, use Talk to the team.';

        return implode("\n", $lines);
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     * @return list<array<string, string>>
     */
    public function cards(array $facts): array
    {
        return array_map(fn (array $fact) => [
            'name' => (string) $fact['name'],
            'sku' => (string) ($fact['sku'] ?? ''),
            'price_label' => (string) $fact['price_label'],
            'availability' => (string) $fact['availability'],
            'warranty' => (string) ($fact['warranty'] ?? ''),
            'url' => (string) $fact['url'],
        ], $facts);
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     */
    protected function complete(string $question, array $facts): ?string
    {
        $catalogue = array_map(function (array $fact) {
            unset($fact['available']);

            return $fact;
        }, $facts);

        try {
            $response = Http::timeout((int) config('chat.openai.timeout', 12))
                ->withToken((string) config('chat.openai.api_key'))
                ->acceptJson()
                ->post(rtrim((string) config('chat.openai.base_url'), '/').'/chat/completions', [
                    'model' => config('chat.openai.model'),
                    'temperature' => 0.1,
                    'max_tokens' => 320,
                    'messages' => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user', 'content' => "Question:\n".$question."\n\nCATALOGUE_JSON:\n".json_encode($catalogue, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)],
                    ],
                ]);
        } catch (Throwable $e) {
            Log::warning('Customer chat OpenAI request failed', ['error' => $e->getMessage()]);

            return null;
        }

        if (! $response->successful()) {
            Log::warning('Customer chat OpenAI request failed', ['status' => $response->status()]);

            return null;
        }

        $content = trim((string) $response->json('choices.0.message.content', ''));

        return $content !== '' ? $content : null;
    }

    protected function systemPrompt(): string
    {
        return 'You are the product assistant for Urban Focus, a South African IT supplier. '
            .'Answer using only CATALOGUE_JSON. '
            .'Never invent specifications, stock, delivery dates or discounts. '
            .'Quote prices only from price_label. If list_price_label is null, do not mention a discount or a lower price. '
            .'Quote availability only with the supplied availability text. '
            .'delivery_guidance is catalogue guidance, not a promised date. Do not add a calendar date. '
            .'If the catalogue does not contain the answer, say so and tell the customer to use Talk to the team. '
            .'Do not approve a return, refund or warranty claim. '
            .'Do not ask for or repeat personal information, order numbers, email addresses or phone numbers. '
            .'Keep the reply under 120 words. Do not mention OpenAI or that you are a language model.';
    }

    protected function normalizeMoney(string $amount): string
    {
        $amount = preg_replace('/[^\d.]/', '', $amount) ?? '';
        if ($amount === '') {
            return '';
        }

        if (! str_contains($amount, '.')) {
            return $amount.'.00';
        }

        return number_format((float) $amount, 2, '.', '');
    }
}
