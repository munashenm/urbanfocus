<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional SMS to staff when a visitor asks for a person.
 * Does nothing unless chat.sms is enabled and the provider credentials exist.
 */
class ChatSmsAlert
{
    public function configured(): bool
    {
        if (! filter_var(config('chat.sms.enabled'), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        if (! filled(config('chat.sms.to'))) {
            return false;
        }

        return match ((string) config('chat.sms.driver', 'bulksms')) {
            'clickatell' => filled(config('chat.sms.clickatell.api_key')),
            default => filled(config('chat.sms.bulksms.token_id')) && filled(config('chat.sms.bulksms.token_secret')),
        };
    }

    public function send(ChatConversation $conversation): void
    {
        if (! $this->configured()) {
            return;
        }

        $name = $conversation->visitor_name ?: 'A visitor';
        $body = $name.' wants to talk to the Urban Focus team. '.route('admin.chat.show', $conversation);

        try {
            $response = match ((string) config('chat.sms.driver', 'bulksms')) {
                'clickatell' => $this->clickatell($body),
                default => $this->bulksms($body),
            };

            if ($response->failed()) {
                Log::warning('Customer chat SMS failed', ['status' => $response->status()]);
            }
        } catch (Throwable $e) {
            Log::warning('Customer chat SMS failed', ['error' => $e->getMessage()]);
        }
    }

    protected function bulksms(string $body): Response
    {
        return Http::timeout(8)
            ->withBasicAuth((string) config('chat.sms.bulksms.token_id'), (string) config('chat.sms.bulksms.token_secret'))
            ->acceptJson()
            ->post((string) config('chat.sms.bulksms.url'), [
                'to' => [['address' => preg_replace('/\D+/', '', (string) config('chat.sms.to'))]],
                'body' => $body,
            ]);
    }

    protected function clickatell(string $body): Response
    {
        return Http::timeout(8)
            ->withToken((string) config('chat.sms.clickatell.api_key'))
            ->acceptJson()
            ->post((string) config('chat.sms.clickatell.url'), [
                'messages' => [[
                    'channel' => 'sms',
                    'to' => preg_replace('/\D+/', '', (string) config('chat.sms.to')),
                    'content' => $body,
                ]],
            ]);
    }
}
