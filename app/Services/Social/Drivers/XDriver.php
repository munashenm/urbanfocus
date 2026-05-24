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
        return $this->hasOAuth1Credentials() || config('social-posting.x.bearer_token');
    }

    public function post(SocialPost $post): bool
    {
        $text = Str::limit($post->message, 280, '');
        $url = 'https://api.twitter.com/2/tweets';

        if ($this->hasOAuth1Credentials()) {
            $response = $this->oauth1JsonPost($url, ['text' => $text]);
        } elseif ($token = config('social-posting.x.bearer_token')) {
            // Must be an OAuth 2.0 *user* access token with tweet.write — not the app-only bearer token.
            $response = Http::withToken($token)->post($url, ['text' => $text]);
        } else {
            $post->markFailed('X API not configured. Set X_API_KEY, X_API_SECRET, X_ACCESS_TOKEN, X_ACCESS_TOKEN_SECRET (recommended) or X_BEARER_TOKEN (OAuth 2 user token with tweet.write).');

            return false;
        }

        if ($response->successful()) {
            $post->markPosted((string) ($response->json('data.id') ?? ''));

            return true;
        }

        $error = $response->json('detail')
            ?: $response->json('title')
            ?: $response->json('errors.0.message')
            ?: $response->body();

        $post->markFailed($error);
        Log::warning('X post failed', ['response' => $response->json() ?: $response->body()]);

        return false;
    }

    protected function hasOAuth1Credentials(): bool
    {
        return config('social-posting.x.api_key')
            && config('social-posting.x.api_secret')
            && config('social-posting.x.access_token')
            && config('social-posting.x.access_token_secret');
    }

    protected function oauth1JsonPost(string $url, array $body): \Illuminate\Http\Client\Response
    {
        $oauth = [
            'oauth_consumer_key' => config('social-posting.x.api_key'),
            'oauth_nonce' => bin2hex(random_bytes(16)),
            'oauth_signature_method' => 'HMAC-SHA1',
            'oauth_timestamp' => (string) time(),
            'oauth_token' => config('social-posting.x.access_token'),
            'oauth_version' => '1.0',
        ];

        $oauth['oauth_signature'] = $this->sign('POST', $url, $oauth);

        $header = 'OAuth '.collect($oauth)
            ->map(fn ($value, $key) => rawurlencode($key).'="'.rawurlencode($value).'"')
            ->implode(', ');

        return Http::withHeaders([
            'Authorization' => $header,
            'Content-Type' => 'application/json',
        ])->post($url, $body);
    }

    protected function sign(string $method, string $url, array $oauthParams): string
    {
        $params = collect($oauthParams)
            ->sortKeys()
            ->map(fn ($value, $key) => rawurlencode($key).'='.rawurlencode($value))
            ->implode('&');

        $baseString = strtoupper($method).'&'.rawurlencode($url).'&'.rawurlencode($params);
        $signingKey = rawurlencode(config('social-posting.x.api_secret')).'&'.rawurlencode(config('social-posting.x.access_token_secret'));

        return base64_encode(hash_hmac('sha1', $baseString, $signingKey, true));
    }
}
