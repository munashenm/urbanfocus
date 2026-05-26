<?php

namespace App\Observers;

use App\Mail\LowStockAlert;
use App\Models\Product;
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
    ) {}

    public function saved(Product $product): void
    {
        $this->safe(fn () => $this->handleSocialQueue($product));
        $this->safe(fn () => $this->handleStockAlerts($product));
        $this->safe(fn () => $this->seo->clearCache());
    }

    public function deleted(Product $product): void
    {
        $this->safe(fn () => $this->seo->clearCache());
    }

    protected function handleSocialQueue(Product $product): void
    {
        if (! $product->wasRecentlyCreated && ! $product->wasChanged('is_active')) {
            return;
        }

        $this->social->queueProduct($product);
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
