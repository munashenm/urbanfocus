<?php

namespace App\Services\Chat;

/**
 * Facts the chat may use, taken from config/chat_knowledge.php plus the live
 * business, shipping, partner and FAQ settings. Nothing here is invented at
 * answer time.
 */
class BusinessKnowledge
{
    /**
     * @return list<array{id: string, title: string, lines: list<string>}>
     */
    public function forQuestion(string $question): array
    {
        $topics = $this->topics();
        $matched = [];

        foreach ($topics as $id => $topic) {
            if ($this->topicMatches($question, $topic)) {
                $matched[$id] = $topic;
            }
        }

        if (isset($matched['services']) && isset($topics['company'])) {
            $matched = ['company' => $topics['company']] + $matched;
        }

        if ($matched === [] && isset($topics['company'], $topics['services'])) {
            $matched = [
                'company' => $topics['company'],
                'services' => $topics['services'],
            ];
        }

        $sections = [];
        foreach ($matched as $id => $topic) {
            $lines = $this->linesFor($id, $topic);
            if ($lines === []) {
                continue;
            }
            $sections[] = [
                'id' => $id,
                'title' => (string) ($topic['title'] ?? $id),
                'lines' => $lines,
            ];
        }

        $sections[] = [
            'id' => 'contact',
            'title' => 'Contact',
            'lines' => $this->contactLines(),
        ];

        $faqs = $this->matchingFaqs($question);
        if ($faqs !== []) {
            $sections[] = [
                'id' => 'faq',
                'title' => 'Published FAQ',
                'lines' => $faqs,
            ];
        }

        $sections[] = [
            'id' => 'limits',
            'title' => 'Limits',
            'lines' => [
                'Answer only about Urban Focus, its products, orders, services and published policies.',
                'Do not answer general knowledge, coding, news, homework, or other companies.',
                'Do not describe IT Guy Solutions or Cyber Developers beyond the names in the company lines.',
                'Do not approve a return, refund or warranty claim.',
                'Do not invent a price, a discount, a stock level, a bank account or a delivery date.',
                'Do not state a company registration number or a VAT number.',
                'If the detail is not in these lines, say so and tell the customer to use Talk to the team.',
            ],
        ];

        return $sections;
    }

    public function matches(string $question): bool
    {
        foreach ($this->topics() as $topic) {
            if ($this->topicMatches($question, $topic)) {
                return true;
            }
        }

        return $this->matchesPhrase($question, 'phone')
            || $this->matchesPhrase($question, 'email')
            || $this->matchesPhrase($question, 'hours')
            || $this->matchesPhrase($question, 'address')
            || $this->matchesPhrase($question, 'contact')
            || $this->matchesPhrase($question, 'call');
    }

    public function refusal(): string
    {
        return $this->copy('refusal', 'I can only help with Urban Focus: our products, services, orders and policies. I cannot answer that. Ask about the catalogue, a quote, delivery or an order, or use Talk to the team.');
    }

    public function greeting(): string
    {
        return $this->copy('greeting', 'Hello. I can help with Urban Focus products, quotes, delivery, orders and policies. What do you need?');
    }

    /**
     * @param  array<string, mixed>  $topic
     * @return list<string>
     */
    protected function linesFor(string $id, array $topic): array
    {
        $lines = array_values(array_filter(array_map(
            fn ($line) => trim((string) $line),
            is_array($topic['lines'] ?? null) ? $topic['lines'] : []
        )));

        if ($id === 'delivery') {
            $lines[] = 'The configured standard courier fee is R '.number_format((float) config('shipping.flat_rate'), 2)
                .'. Free shipping applies on orders over R '.number_format((float) config('shipping.free_threshold'), 2)
                .'. Those amounts come from the shipping settings. They are not a quote for a heavy or bulk order.';
        }

        if ($id === 'brands') {
            $names = $this->names(config('partners.default_brands'));
            if ($names !== []) {
                $lines[] = 'Brand logos shown on the website: '.implode(', ', $names).'.';
            }
        }

        if ($id === 'payment') {
            $names = $this->names(config('partners.payments'));
            if ($names !== []) {
                $lines[] = 'Payment marks displayed on the site: '.implode(', ', $names).'.';
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected function contactLines(): array
    {
        $address = array_filter([
            config('business.address.line1'),
            config('business.address.line2'),
            config('business.address.city'),
            config('business.address.province'),
            config('business.address.country'),
        ], fn ($part) => is_string($part) && trim($part) !== '');

        $phone = (string) config('business.phone');
        $email = (string) config('business.email');
        $hours = (string) config('business.hours');

        return array_values(array_filter([
            'Phone: '.$phone.'.',
            'Email: '.$email.'.',
            'Published hours: '.$hours.' (Africa/Johannesburg). The chat treats Monday to Friday 08:00–17:00 as open.',
            $address === [] ? null : 'Address: '.implode(', ', $address).'.',
            'The contact page says the team typically responds within one business day.',
            'For a person, use Talk to the team in this chat. This chat does not approve a return, a refund or a warranty claim.',
        ]));
    }

    /**
     * @return list<string>
     */
    protected function matchingFaqs(string $question): array
    {
        $faqs = config('seo.faq');
        if (! is_array($faqs)) {
            return [];
        }

        $words = $this->keywords($question);
        $lines = [];

        foreach ($faqs as $faq) {
            if (! is_array($faq)) {
                continue;
            }
            $haystack = mb_strtolower((string) ($faq['question'] ?? '').' '.(string) ($faq['answer'] ?? ''));
            foreach ($words as $word) {
                if (str_contains($haystack, $word)) {
                    $lines[] = trim((string) ($faq['question'] ?? '').' '.(string) ($faq['answer'] ?? ''));
                    break;
                }
            }
            if (count($lines) >= 3) {
                break;
            }
        }

        return $lines;
    }

    /**
     * @return list<string>
     */
    protected function keywords(string $question): array
    {
        $raw = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($question), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $skip = array_flip(['what', 'which', 'when', 'where', 'how', 'does', 'do', 'you', 'your', 'the', 'and', 'for', 'with', 'about', 'please']);
        $words = [];
        foreach ($raw as $word) {
            if (strlen($word) < 5 || isset($skip[$word])) {
                continue;
            }
            $words[] = $word;
        }

        return array_values(array_unique($words));
    }

    /**
     * @param  array<string, mixed>  $topic
     */
    protected function topicMatches(string $question, array $topic): bool
    {
        $triggers = $topic['triggers'] ?? [];
        if (! is_array($triggers)) {
            return false;
        }

        foreach ($triggers as $trigger) {
            if (is_string($trigger) && $trigger !== '' && $this->matchesPhrase($question, $trigger)) {
                return true;
            }
        }

        return false;
    }

    protected function matchesPhrase(string $question, string $trigger): bool
    {
        $question = mb_strtolower($question);
        $trigger = mb_strtolower(trim($trigger));
        if ($trigger === '') {
            return false;
        }

        if (str_contains($trigger, ' ')) {
            return str_contains($question, $trigger);
        }

        return (bool) preg_match('/\b'.preg_quote($trigger, '/').'\b/u', $question);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    protected function topics(): array
    {
        $topics = config('chat_knowledge.topics');
        if (is_array($topics) && $topics !== []) {
            return $topics;
        }

        $path = config_path('chat_knowledge.php');
        if (! is_file($path)) {
            return [];
        }

        $loaded = require $path;

        return is_array($loaded['topics'] ?? null) ? $loaded['topics'] : [];
    }

    protected function copy(string $key, string $default): string
    {
        $value = config('chat_knowledge.'.$key);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $path = config_path('chat_knowledge.php');
        if (is_file($path)) {
            $loaded = require $path;
            if (is_string($loaded[$key] ?? null) && $loaded[$key] !== '') {
                return $loaded[$key];
            }
        }

        return $default;
    }

    /**
     * @return list<string>
     */
    protected function names(mixed $rows): array
    {
        if (! is_array($rows)) {
            return [];
        }

        $names = [];
        foreach ($rows as $row) {
            if (is_array($row) && isset($row['name']) && is_string($row['name']) && $row['name'] !== '') {
                $names[] = $row['name'];
            }
        }

        return $names;
    }
}
