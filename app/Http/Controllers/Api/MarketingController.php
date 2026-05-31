<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Product;
use App\Services\Marketing\MakeWebhookService;
use App\Services\Social\SocialCaptionService;
use Illuminate\Http\JsonResponse;

/**
 * Sends product and blog data (with AI captions) to the Make.com webhooks that
 * drive Urban Focus social publishing. Protected by the api.key middleware.
 */
class MarketingController extends Controller
{
    public function __construct(
        protected MakeWebhookService $make,
        protected SocialCaptionService $captions,
    ) {}

    public function dispatchProduct(string $identifier): JsonResponse
    {
        $product = $this->findProduct($identifier);

        $result = $this->make->dispatchProduct($product, 'product.manual');

        return $this->respond($result);
    }

    public function dispatchArticle(string $identifier): JsonResponse
    {
        $article = $this->findArticle($identifier);

        $result = $this->make->dispatchArticle($article, 'blog.manual');

        return $this->respond($result);
    }

    /** Preview the caption payload without sending it to Make.com. */
    public function previewProduct(string $identifier): JsonResponse
    {
        return response()->json(['data' => $this->captions->forProduct($this->findProduct($identifier))]);
    }

    public function previewArticle(string $identifier): JsonResponse
    {
        return response()->json(['data' => $this->captions->forArticle($this->findArticle($identifier))]);
    }

    protected function findProduct(string $identifier): Product
    {
        return Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)
                    ->orWhere('sku', $identifier)
                    ->orWhere('id', $identifier);
            })
            ->firstOrFail();
    }

    protected function findArticle(string $identifier): Article
    {
        return Article::published()
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)->orWhere('id', $identifier);
            })
            ->firstOrFail();
    }

    /**
     * @param  array{ok: bool, log: \App\Models\WebhookLog|null, reason?: string}  $result
     */
    protected function respond(array $result): JsonResponse
    {
        if ($result['ok']) {
            return response()->json([
                'status' => 'dispatched',
                'webhook_log_id' => $result['log']?->id,
                'platforms' => $result['log']?->platformList() ?? [],
            ]);
        }

        return response()->json([
            'status' => 'failed',
            'reason' => $result['reason'] ?? 'Dispatch failed',
            'webhook_log_id' => $result['log']?->id,
        ], $result['log'] ? 502 : 409);
    }
}
