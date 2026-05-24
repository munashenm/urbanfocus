<?php

namespace App\Services\Social\Drivers;

use App\Models\SocialPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaDriver
{
    public function isConfigured(): bool
    {
        return config('social-posting.meta.page_access_token') && config('social-posting.meta.page_id');
    }

    public function postToFacebook(SocialPost $post): bool
    {
        if (! $this->isConfigured()) {
            $post->markFailed('Meta API not configured (META_PAGE_ID, META_PAGE_ACCESS_TOKEN)');

            return false;
        }

        $version = config('social-posting.meta.graph_version');
        $pageId = config('social-posting.meta.page_id');
        $token = config('social-posting.meta.page_access_token');

        $response = Http::asForm()->post("https://graph.facebook.com/{$version}/{$pageId}/feed", array_filter([
            'message' => $post->message,
            'link' => $post->link_url,
            'access_token' => $token,
        ]));

        if ($response->successful()) {
            $post->markPosted($response->json('id'));

            return true;
        }

        $post->markFailed($response->json('error.message') ?: $response->body());
        Log::warning('Facebook post failed', ['response' => $response->json()]);

        return false;
    }

    public function postToInstagram(SocialPost $post): bool
    {
        $igId = config('social-posting.meta.instagram_account_id');
        $token = config('social-posting.meta.page_access_token');

        if (! $igId || ! $token) {
            $post->markFailed('Instagram not configured (META_INSTAGRAM_ACCOUNT_ID)');

            return false;
        }

        if (! $post->image_url || str_contains($post->image_url, 'product-placeholder')) {
            $post->markFailed('Instagram requires a product/article image');

            return false;
        }

        $version = config('social-posting.meta.graph_version');
        $base = "https://graph.facebook.com/{$version}/{$igId}";

        $container = Http::asForm()->post("{$base}/media", [
            'image_url' => $post->image_url,
            'caption' => $post->message,
            'access_token' => $token,
        ]);

        if (! $container->successful()) {
            $post->markFailed($container->json('error.message') ?: 'Instagram media container failed');

            return false;
        }

        $creationId = $container->json('id');
        $publish = Http::asForm()->post("{$base}/media_publish", [
            'creation_id' => $creationId,
            'access_token' => $token,
        ]);

        if ($publish->successful()) {
            $post->markPosted($publish->json('id'));

            return true;
        }

        $post->markFailed($publish->json('error.message') ?: 'Instagram publish failed');

        return false;
    }
}
