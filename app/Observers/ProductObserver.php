<?php

namespace App\Observers;

use App\Mail\LowStockAlert;
use App\Models\Product;
use App\Services\Social\SocialPostingService;
use App\Services\StockAlertService;
use Illuminate\Support\Facades\Mail;

class ProductObserver
{
    public function __construct(
        protected SocialPostingService $social,
        protected StockAlertService $stockAlerts,
    ) {}

    public function saved(Product $product): void
    {
        if ($product->wasRecentlyCreated || $product->wasChanged('is_active')) {
            $this->social->queueProduct($product);
        }

        if (! $product->manage_stock) {
            return;
        }

        if ($product->wasChanged('stock_quantity')) {
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
    }

    protected function sendLowStockAlert(Product $product): void
    {
        $email = config('inventory.alert_email');
        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new LowStockAlert($product));
        } catch (\Throwable) {
            // avoid breaking product save
        }
    }
}
