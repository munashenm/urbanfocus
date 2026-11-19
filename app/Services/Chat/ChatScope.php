<?php

namespace App\Services\Chat;

/**
 * Decides whether a customer message is about Urban Focus, and refuses the rest
 * before any catalogue search or model call.
 */
class ChatScope
{
    public const PRODUCT = 'product';

    public const BUSINESS = 'business';

    public const OFF_TOPIC = 'off_topic';

    public const GREETING = 'greeting';

    public function __construct(protected BusinessKnowledge $knowledge) {}

    public function classify(string $question): string
    {
        $question = trim($question);
        if ($question === '') {
            return self::OFF_TOPIC;
        }

        if ($this->isInjection($question) || $this->isClearlyOffTopic($question)) {
            return self::OFF_TOPIC;
        }

        if ($this->isGreeting($question)) {
            return self::GREETING;
        }

        if ($this->isProduct($question)) {
            return self::PRODUCT;
        }

        if ($this->knowledge->matches($question)) {
            return self::BUSINESS;
        }

        return self::OFF_TOPIC;
    }

    public function isProduct(string $question): bool
    {
        return ! $this->isInjection($question) && ! $this->isClearlyOffTopic($question) && $this->hasSpecificProduct($question);
    }

    public function refusal(): string
    {
        return $this->knowledge->refusal();
    }

    public function greeting(): string
    {
        return $this->knowledge->greeting();
    }

    protected function isGreeting(string $question): bool
    {
        return (bool) preg_match('/^(hi|hello|hey|howzit|good (morning|afternoon|evening))[!.?\s]*$/iu', trim($question));
    }

    protected function isInjection(string $question): bool
    {
        return (bool) preg_match(
            '/ignore (all |any )?(previous|prior|above) (instructions|rules)|'
            .'\b(system prompt|developer mode|jailbreak|you are now|act as|disregard the rules)\b/iu',
            $question
        );
    }

    protected function isClearlyOffTopic(string $question): bool
    {
        return (bool) preg_match(
            '/\b(python|javascript|typescript|php|sql query|html|css)\b|'
            .'\b(write|debug)\b.{0,40}\b(function|script|program|code)\b|'
            .'\b(capital of|president of|prime minister|weather|recipe|joke|poem|lyrics|homework|essay|translate)\b|'
            .'\b(takealot|incredible connection|wootware|evetech)\b|'
            .'\b(chatgpt|openai|claude|gemini|language model)\b|'
            .'\b(election|football|soccer|cricket|headline)\b|'
            .'\bnews\b/iu',
            $question
        );
    }

    protected function hasSpecificProduct(string $question): bool
    {
        if (preg_match('/\b[A-Z]{2,}-[A-Z0-9-]{2,}\b/', $question)) {
            return true;
        }

        $question = mb_strtolower($question);

        $patterns = [
            '/\b(sku|model number|part number)\b/u',
            '/\b(laptop|laptops|notebook|notebooks|desktop|desktops|server|servers|switch|switches|router|routers)\b/u',
            '/\b(camera|cameras|cctv|nvr|printer|printers|monitor|monitors|firewall|firewalls)\b/u',
            '/\b(nas|ups|tablet|tablets|workstation|workstations|dock|docking|ssd|hdd|storage)\b/u',
            '/\b(licence|license|licensing|access point|wi-?fi|fibre|fiber|voip)\b/u',
            '/\ball[ -]in[ -]one\b/u',
            '/\b(keyboard|mouse|webcam|headset|graphics card)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $question)) {
                return true;
            }
        }

        foreach ($this->brands() as $brand) {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/u', $question)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    protected function brands(): array
    {
        $names = ['unifi', 'ubiquiti', 'mikrotik', 'microtik', 'hp', 'dell', 'lenovo', 'hikvision', 'dahua', 'yealink', 'sophos', 'starlink', 'cambium', 'tp-link', 'tplink', 'logitech', 'samsung', 'huawei', 'kuycon', 'lg', 'microsoft'];

        $configured = config('partners.default_brands');
        if (is_array($configured)) {
            foreach ($configured as $brand) {
                if (is_array($brand) && isset($brand['name']) && is_string($brand['name'])) {
                    $names[] = mb_strtolower($brand['name']);
                }
            }
        }

        return array_values(array_unique($names));
    }
}
