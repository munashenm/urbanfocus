<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\Social\SocialPostingService;

class ProductObserver
{
    public function __construct(protected SocialPostingService $social) {}

    public function saved(Product $product): void
    {
        if ($product->wasRecentlyCreated || $product->wasChanged('is_active')) {
            $this->social->queueProduct($product);
        }
    }
}
