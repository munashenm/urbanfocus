<?php

namespace App\Services\Marketing;

use App\Jobs\DispatchMakeWebhook;
use App\Models\Article;
use App\Models\Product;
use App\Models\SocialPost;
use App\Models\WebhookLog;
use App\Services\Social\SocialCaptionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Sends product and blog events to Make.com webhooks. Make.com scenarios then
 * publish to Facebook, LinkedIn and X using the AI captions in the payload.
 *
 * Every dispatch is recorded in two places that surface in the admin dashboard:
 *   - a WebhookLog row (the raw webhook delivery, success or failure)
 *   - one SocialPost row per platform (the publishing attempt per channel)
 */
class MakeWebhookService
{
    /** Set true to skip dispatch (used during bulk imports/seeders). */
    public static bool $suppress = false;

    public function __construct(
        protected SocialCaptionService $captions,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('make.enabled');
    }

    /**
     * Queue a product webhook so the HTTP call runs off the request lifecycle
     * (never blocking an admin save). No-op when disabled or suppressed.
     */
    public function queueProduct(Product $product, string $event = 'product.published'): void
    {
        if (! $this->shouldDispatch()) {
            return;
        }

        $this->queueJob(new DispatchMakeWebhook($product, $event));
    }

    /**
     * Queue a blog article webhook. No-op when disabled or suppressed.
     */
    public function queueArticle(Article $article, string $event = 'blog.published'): void
    {
        if (! $this->shouldDispatch()) {
            return;
        }

        $this->queueJob(new DispatchMakeWebhook($article, $event));
    }

    protected function queueJob(DispatchMakeWebhook $job): void
    {
        if ($connection = config('make.queue_connection')) {
            $job->onConnection($connection);
        }

        if ($queue = config('make.queue')) {
            $job->onQueue($queue);
        }

        dispatch($job);
    }

    /**
     * Dispatch a product to the Make.com product webhook.
     *
     * @return array{ok: bool, log: WebhookLog|null, reason?: string}
     */
    public function dispatchProduct(Product $product, string $event = 'product.published'): array
    {
        if (! $this->shouldDispatch()) {
            return ['ok' => false, 'log' => null, 'reason' => 'Make.com integration disabled'];
        }

        $url = config('make.webhooks.product');

        if (! $url) {
            return ['ok' => false, 'log' => null, 'reason' => 'MAKE_PRODUCT_WEBHOOK_URL is not set'];
        }

        $bundle = $this->captions->forProduct($product);

        return $this->send($url, $event, $product, $product->name, $bundle);
    }

    /**
     * Dispatch a blog article to the Make.com blog webhook.
     *
     * @return array{ok: bool, log: WebhookLog|null, reason?: string}
     */
    public function dispatchArticle(Article $article, string $event = 'blog.published'): array
    {
        if (! $this->shouldDispatch()) {
            return ['ok' => false, 'log' => null, 'reason' => 'Make.com integration disabled'];
        }

        $url = config('make.webhooks.blog');

        if (! $url) {
            return ['ok' => false, 'log' => null, 'reason' => 'MAKE_BLOG_WEBHOOK_URL is not set'];
        }

        $bundle = $this->captions->forArticle($article);

        return $this->send($url, $event, $article, $article->title, $bundle);
    }

    /**
     * @param  array<string, mixed>  $bundle
     * @return array{ok: bool, log: WebhookLog, reason?: string}
     */
    protected function send(string $url, string $event, Product|Article $model, string $label, array $bundle): array
    {
        $platforms = array_keys($bundle['captions'] ?? []);

        $payload = array_merge(
            [
                'event' => $event,
                'site' => config('app.name', 'Urban Focus'),
                'platforms' => $platforms,
                'dispatched_at' => now()->toIso8601String(),
            ],
            $bundle,
        );

        $log = WebhookLog::create([
            'event' => $event,
            'target_type' => $model::class,
            'target_id' => $model->id,
            'target_label' => Str::limit($label, 250),
            'destination' => 'make',
            'webhook_url' => $url,
            'platforms' => $platforms,
            'payload' => $payload,
            'status' => 'pending',
        ]);

        try {
            $response = Http::timeout((int) config('make.timeout', 15))
                ->retry((int) config('make.retries', 2), 500, throw: false)
                ->withHeaders($this->headers())
                ->post($url, $payload);

            if ($response->successful()) {
                $log->markSuccess($response->status(), $response->body());
                $this->recordPlatformPosts($model, $bundle, 'posted', null);

                return ['ok' => true, 'log' => $log->fresh()];
            }

            $error = 'Make.com returned HTTP '.$response->status();
            $log->markFailed($error, $response->status(), $response->body());
            $this->recordPlatformPosts($model, $bundle, 'failed', $error);
            Log::warning('Make.com webhook failed', ['event' => $event, 'status' => $response->status()]);

            return ['ok' => false, 'log' => $log->fresh(), 'reason' => $error];
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            $this->recordPlatformPosts($model, $bundle, 'failed', $e->getMessage());
            Log::warning('Make.com webhook exception', ['event' => $event, 'error' => $e->getMessage()]);

            return ['ok' => false, 'log' => $log->fresh(), 'reason' => $e->getMessage()];
        }
    }

    /**
     * Record one SocialPost per platform so the Social Media dashboard shows a
     * publishing attempt for every channel Make.com was asked to post to.
     *
     * @param  array<string, mixed>  $bundle
     */
    protected function recordPlatformPosts(Product|Article $model, array $bundle, string $status, ?string $error): void
    {
        $captions = $bundle['captions'] ?? [];
        $linkUrl = $bundle['url'] ?? null;
        $imageUrl = $bundle['image_url'] ?? null;

        foreach ($captions as $platform => $caption) {
            $post = SocialPost::updateOrCreate(
                [
                    'postable_type' => $model::class,
                    'postable_id' => $model->id,
                    'platform' => $platform,
                ],
                [
                    'message' => $caption,
                    'link_url' => $linkUrl,
                    'image_url' => $imageUrl,
                ]
            );

            if ($status === 'posted') {
                $post->markPosted('make:'.now()->timestamp);
            } else {
                $post->markFailed($error ?: 'Make.com dispatch failed');
            }
        }
    }

    /** @return array<string, string> */
    protected function headers(): array
    {
        $headers = ['Accept' => 'application/json'];

        if ($secret = config('make.secret')) {
            $headers['X-Make-Secret'] = $secret;
        }

        return $headers;
    }

    protected function shouldDispatch(): bool
    {
        return $this->isEnabled() && ! static::$suppress;
    }
}
