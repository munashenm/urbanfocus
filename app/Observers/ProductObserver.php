<?php

namespace App\Observers;

use App\Mail\LowStockAlert;
use App\Models\Product;
use App\Services\CatalogDeduper;
use App\Services\IndexNowService;
use App\Services\Marketing\MakeWebhookService;
use App\Services\SeoService;
use App\Services\Social\SocialPostingService;
use App\Services\StockAlertService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class ProductObserver
{
    public function __construct(
        protected SocialPostingService $social,
        protected StockAlertService $stockAlerts,
        protected SeoService $seo,
        protected MakeWebhookService $make,
        protected IndexNowService $indexNow,
    ) {}

    public function saved(Product $product): void
    {
        $this->safe(fn () => $this->handleSocialQueue($product));
        $this->safe(fn () => $this->handleMakeWebhook($product));
        $this->safe(fn () => $this->handleStockAlerts($product));
        $this->safe(fn () => $this->handleSearchIndex($product));
        $this->safe(fn () => app(CatalogDeduper::class)->clearCache());
    }

    public function deleted(Product $product): void
    {
        $this->safe(fn () => $this->seo->clearCache());
        $this->safe(fn () => app(CatalogDeduper::class)->clearCache());
        $this->safe(function () use ($product) {
            if ($product->slug) {
                $this->indexNow->notify(route('products.show', $product));
            }
        });
    }

    protected function handleSocialQueue(Product $product): void
    {
        if (! $product->wasRecentlyCreated && ! $product->wasChanged('is_active')) {
            return;
        }

        $this->social->queueProduct($product);
    }

    protected function handleMakeWebhook(Product $product): void
    {
        if (! $this->make->isEnabled() || ! $product->is_active) {
            return;
        }

        // Fire only when the product is first published or (re)activated.
        $becamePublished = $product->wasRecentlyCreated
            || ($product->wasChanged('is_active') && $product->is_active);

        if (! $becamePublished) {
            return;
        }

        // Queued so the webhook HTTP call never blocks the admin save.
        $this->make->queueProduct($product);
    }

    protected function handleStockAlerts(Product $product): void
    {
        if ($product->wasRecentlyCreated || ! $product->manage_stock || ! $product->wasChanged('stock_quantity')) {
            return;
        }

        if (! Schema::hasTable('stock_alerts')) {
            return;
        }

        $previous = (int) $product->getOriginal('stock_quantity');

        if ($previous <= 0 && $product->stock_quantity > 0 && $product->is_active) {
            $this->stockAlerts->notifyWaitlist($product);
        }

        $threshold = config('inventory.low_stock_threshold', 5);
        if ($product->stock_quantity > 0
            && $product->stock_quantity <= $threshold
            && $previous > $threshold) {
            $this->sendLowStockAlert($product);
        }
    }

    protected function handleSearchIndex(Product $product): void
    {
        if (! $this->isSearchRelevantChange($product)) {
            return;
        }

        $this->seo->clearCache();

        if (! $product->is_active && ! $product->wasChanged('is_active')) {
            return;
        }

        $this->indexNow->notify(route('products.show', $product));

        if ($product->category) {
            $this->indexNow->notify($product->category->url());
        }
    }

    protected function isSearchRelevantChange(Product $product): bool
    {
        if ($product->wasRecentlyCreated) {
            return true;
        }

        return $product->wasChanged([
            'name',
            'slug',
            'price',
            'sale_price',
            'stock_quantity',
            'in_stock',
            'is_active',
            'short_description',
            'description',
            'brand',
            'sku',
            'barcode',
            'model_number',
            'category_id',
            'meta_title',
            'meta_description',
            'warranty_months',
            'specifications',
        ]);
    }

    protected function sendLowStockAlert(Product $product): void
    {
        $email = config('inventory.alert_email');
        if (! $email) {
            return;
        }

        Mail::to($email)->send(new LowStockAlert($product));
    }

    protected function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable) {
            // Observers must never block admin product saves.
        }
    }
}
