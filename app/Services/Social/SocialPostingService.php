<?php

namespace App\Services\Social;

use App\Models\Article;
use App\Models\Product;
use App\Models\SocialPost;
use App\Services\Social\Drivers\MetaDriver;
use App\Services\Social\Drivers\TikTokDriver;
use App\Services\Social\Drivers\XDriver;

class SocialPostingService
{
    public static bool $suppress = false;

    public function __construct(
        protected SocialPostFormatter $formatter,
        protected MetaDriver $meta,
        protected XDriver $x,
        protected TikTokDriver $tiktok,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('social-posting.enabled');
    }

    public function queueProduct(Product $product): void
    {
        if (! $this->shouldQueue() || ! $product->is_active) {
            return;
        }

        $product->loadMissing('images');
        $payload = $this->formatter->product($product);
        $this->createPendingPosts($product, $payload);
    }

    public function queueArticle(Article $article): void
    {
        if (! $this->shouldQueue() || ! $article->is_published) {
            return;
        }

        $payload = $this->formatter->article($article);
        $this->createPendingPosts($article, $payload);
    }

    /** @return array{posted: int, failed: int, skipped: int} */
    public function publishPending(?int $limit = null): array
    {
        $limit = $limit ?? config('social-posting.max_per_run', 5);
        $posted = 0;
        $failed = 0;
        $skipped = 0;

        $pending = SocialPost::where('status', 'pending')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        foreach ($pending as $socialPost) {
            if (! config("social-posting.platforms.{$socialPost->platform}", false)) {
                $socialPost->update(['status' => 'skipped', 'error_message' => 'Platform disabled']);
                $skipped++;

                continue;
            }

            $success = match ($socialPost->platform) {
                'facebook' => $this->meta->postToFacebook($socialPost),
                'instagram' => $this->meta->postToInstagram($socialPost),
                'x' => $this->x->post($socialPost),
                'tiktok' => $this->tiktok->post($socialPost),
                default => tap(false, fn () => $socialPost->markFailed('Unknown platform')),
            };

            $success ? $posted++ : $failed++;
        }

        return compact('posted', 'failed', 'skipped');
    }

    public function retryFailed(): int
    {
        return SocialPost::where('status', 'failed')->update([
            'status' => 'pending',
            'error_message' => null,
        ]);
    }

    /** @return array{queued: int} */
    public function queueAllActiveProducts(): array
    {
        $queued = 0;
        Product::where('is_active', true)->with('images')->orderBy('id')->chunk(50, function ($products) use (&$queued) {
            foreach ($products as $product) {
                $before = SocialPost::where('postable_type', Product::class)->where('postable_id', $product->id)->count();
                $this->queueProduct($product);
                $after = SocialPost::where('postable_type', Product::class)->where('postable_id', $product->id)->count();
                if ($after > $before) {
                    $queued++;
                }
            }
        });

        return ['queued' => $queued];
    }

    protected function shouldQueue(): bool
    {
        return $this->isEnabled() && ! static::$suppress;
    }

    protected function createPendingPosts(Product|Article $model, array $payload): void
    {
        foreach (array_keys(array_filter(config('social-posting.platforms', []))) as $platform) {
            $post = SocialPost::firstOrCreate(
                [
                    'postable_type' => $model::class,
                    'postable_id' => $model->id,
                    'platform' => $platform,
                ],
                [
                    'status' => 'pending',
                    'message' => $payload['message'],
                    'link_url' => $payload['link_url'],
                    'image_url' => $payload['image_url'],
                ]
            );

            if ($post->status !== 'posted') {
                $post->update([
                    'status' => 'pending',
                    'message' => $payload['message'],
                    'link_url' => $payload['link_url'],
                    'image_url' => $payload['image_url'],
                    'error_message' => null,
                ]);
            }
        }
    }
}
