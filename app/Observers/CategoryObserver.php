<?php

namespace App\Observers;

use App\Models\Category;
use App\Services\IndexNowService;
use App\Services\SeoService;

class CategoryObserver
{
    public function __construct(
        protected SeoService $seo,
        protected IndexNowService $indexNow,
    ) {}

    public function saved(Category $category): void
    {
        if (! $this->shouldNotify($category)) {
            return;
        }

        $this->safe(fn () => $this->seo->clearCache());

        $this->safe(function () use ($category) {
            $this->indexNow->notify($category->url());
            if ($category->wasChanged('slug') && $category->getOriginal('slug')) {
                $this->indexNow->notify(url('/category/'.$category->getOriginal('slug')));
            }
        });
    }

    public function deleted(Category $category): void
    {
        $this->safe(fn () => $this->seo->clearCache());
        $this->safe(fn () => $this->indexNow->notify($category->url()));
    }

    protected function shouldNotify(Category $category): bool
    {
        if ($category->wasRecentlyCreated) {
            return (bool) $category->is_active;
        }

        return $category->wasChanged([
            'name',
            'slug',
            'description',
            'is_active',
            'parent_id',
            'meta_title',
            'meta_description',
        ]);
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
