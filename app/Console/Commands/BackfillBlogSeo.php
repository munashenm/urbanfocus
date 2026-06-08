<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Services\Blog\BlogSchema;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BackfillBlogSeo extends Command
{
    protected $signature = 'blog:backfill-seo {--published : Only process published articles}';

    protected $description = 'Re-run SEO meta, internal linking and social snippets over existing articles';

    public function handle(): int
    {
        if (! BlogSchema::hasArticlesTable()) {
            $this->error('Articles table not found — run migrations first.');

            return self::FAILURE;
        }

        $query = Article::query();

        if ($this->option('published')) {
            $query->where('is_published', true);
        }

        $processed = 0;
        $updated = 0;

        $query->chunkById(100, function ($articles) use (&$processed, &$updated) {
            foreach ($articles as $article) {
                // Saving triggers ArticleObserver::saving() -> automation pipeline.
                // The pipeline only fills empty fields and is idempotent, so
                // unchanged articles produce no write.
                $article->save();

                $processed++;
                if ($article->wasChanged()) {
                    $updated++;
                }
            }
        });

        Cache::forget('sitemap.xml');

        $this->info("Processed {$processed} article(s); {$updated} updated.");

        return self::SUCCESS;
    }
}
