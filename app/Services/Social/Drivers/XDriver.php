<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XDriver
{
    public function isConfigured(): bool
    {
        return config('social-posting.x.bearer_token')
            || (config('social-posting.x.api_key') && config('social-posting.x.access_token'));
    }

    public function post(SocialPost $post): bool
    {
        $token = config('social-posting.x.bearer_token');

        if (! $token) {
            $post->markFailed('X API not configured (X_BEARER_TOKEN or OAuth credentials)');

            return false;
        }

        $response = Http::withToken($token)
            ->post('https://api.twitter.com/2/tweets', [
                'text' => Str::limit($post->message, 280, ''),
            ]);

        if ($response->successful()) {
            $post->markPosted($response->json('data.id'));

            return true;
        }

        $post->markFailed($response->json('detail') ?: $response->json('title') ?: $response->body());
        Log::warning('X post failed', ['response' => $response->json()]);

        return false;
    }
}
