<?php

namespace App\Console\Commands;

use App\Services\Blog\BlogSearchConsoleService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncBlogSearchConsole extends Command
{
    protected $signature = 'blog:sync-search-console';

    protected $description = 'Pull Google Search Console analytics for the content strategy dashboard';

    public function handle(BlogSearchConsoleService $gsc): int
    {
        try {
            $payload = $gsc->fetchSearchAnalytics();
            Cache::forget('sitemap.xml');

            if ($payload === null) {
                $this->warn('GSC is disabled or credentials missing. Set GSC_ENABLED and GSC_CREDENTIALS_PATH.');

                return self::SUCCESS;
            }

            $summary = $payload['summary'] ?? [];
            $this->info('GSC snapshot saved.');
            $this->line('Clicks: '.($summary['total_clicks'] ?? 0));
            $this->line('Impressions: '.($summary['total_impressions'] ?? 0));
            $this->line('Avg CTR: '.($summary['avg_ctr'] ?? 0).'%');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
