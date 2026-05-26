<?php

namespace App\Observers;

use App\Models\Article;
use App\Services\SeoService;
use App\Services\Social\SocialPostingService;

class ArticleObserver
{
    public function __construct(
        protected SocialPostingService $social,
        protected SeoService $seo,
    ) {}

    public function saved(Article $article): void
    {
        if ($article->is_published && ($article->wasRecentlyCreated || $article->wasChanged('is_published'))) {
            $this->social->queueArticle($article);
        }

        try {
            $this->seo->clearCache();
        } catch (\Throwable) {
            // Non-blocking.
        }
    }
}
