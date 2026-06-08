<?php

namespace App\Console\Commands;

use App\Services\NewsSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class SyncNews extends Command
{
    protected $signature = 'news:sync';

    protected $description = 'Import the latest IT news from configured RSS feeds and NewsAPI';

    public function handle(NewsSyncService $sync): int
    {
        $result = $sync->sync();
        Cache::forget('sitemap.xml');

        $this->info("Imported {$result['imported']} new article(s), skipped {$result['skipped']} duplicate(s).");

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
