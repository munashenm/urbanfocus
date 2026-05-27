<?php

namespace App\Services\Blog;

use App\Models\Article;

class BlogAutomationService
{
    public function __construct(
        protected BlogSeoService $seo,
        protected BlogInternalLinkingService $linking,
        protected BlogSocialSnippetService $social,
    ) {}

    public function process(Article $article, bool $save = true): Article
    {
        if (! \App\Services\Blog\BlogSchema::hasArticlesTable()) {
            return $article;
        }

        $article = $this->seo->optimize($article);

        if (config('blog_automation.auto_internal_links', true) && $article->content && \App\Services\Blog\BlogSchema::hasArticlesTable()) {
            $article->content = $this->linking->enrich($article);
        }

        if (config('blog_automation.auto_social_snippets', true) && BlogSchema::hasColumn('social_snippets')) {
            $article->social_snippets = $this->social->generate($article);
        }

        return BlogSchema::stripUnknownAttributes($article);
    }
}
