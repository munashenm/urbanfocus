<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TikTokDriver
{
    public function isConfigured(): bool
    {
        return config('social-posting.tiktok.access_token') && config('social-posting.tiktok.client_key');
    }

    public function post(SocialPost $post): bool
    {
        if (! $this->isConfigured()) {
            $post->markFailed('TikTok API not configured (TIKTOK_CLIENT_KEY, TIKTOK_ACCESS_TOKEN)');

            return false;
        }

        if (! $post->image_url || str_contains($post->image_url, 'product-placeholder')) {
            $post->markFailed('TikTok photo post requires an image URL');

            return false;
        }

        $response = Http::withToken(config('social-posting.tiktok.access_token'))
            ->post('https://open.tiktokapis.com/v2/post/publish/content/init/', [
                'post_info' => [
                    'title' => Str::limit($post->message, 150, ''),
                    'privacy_level' => 'PUBLIC_TO_EVERYONE',
                    'disable_comment' => false,
                ],
                'source_info' => [
                    'source' => 'PULL_FROM_URL',
                    'photo_cover_index' => 0,
                    'photo_images' => [$post->image_url],
                ],
                'post_mode' => 'DIRECT_POST',
                'media_type' => 'PHOTO',
            ]);

        if ($response->successful()) {
            $post->markPosted($response->json('data.publish_id') ?: 'tiktok');

            return true;
        }

        $post->markFailed($response->json('error.message') ?: $response->body());
        Log::warning('TikTok post failed', ['response' => $response->json()]);

        return false;
    }
}
