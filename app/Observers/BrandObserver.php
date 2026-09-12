<?php

namespace App\Observers;

use App\Models\Brand;
use App\Services\IndexNowService;
use App\Services\SeoService;

class BrandObserver
{
    public function __construct(
        protected SeoService $seo,
        protected IndexNowService $indexNow,
    ) {}

    public function saved(Brand $brand): void
    {
        if (! $brand->wasRecentlyCreated && ! $brand->wasChanged(['name', 'slug', 'is_active', 'logo', 'website'])) {
            return;
        }

        $this->safe(fn () => $this->seo->clearCache());

        if (! $brand->is_active && ! $brand->wasChanged('is_active')) {
            return;
        }

        $this->safe(fn () => $this->indexNow->notify(route('brands.show', $brand)));
    }

    public function deleted(Brand $brand): void
    {
        $this->safe(fn () => $this->seo->clearCache());
        $this->safe(fn () => $this->indexNow->notify(route('brands.show', $brand)));
    }

    protected function safe(callable $callback): void
    {
        try {
            $callback();
        } catch (\Throwable) {
            // Observers must never block admin saves.
        }
    }
}
