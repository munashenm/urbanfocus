<?php

namespace App\Console\Commands;

use App\Services\Blog\BlogTopicDiscoveryService;
use Illuminate\Console\Command;

class DiscoverBlogTopics extends Command
{
    protected $signature = 'blog:discover-topics';

    protected $description = 'Discover trending blog topics from Reddit, NewsAPI, YouTube, RSS and Google Trends';

    public function handle(BlogTopicDiscoveryService $discovery): int
    {
        $result = $discovery->discover();

        $this->info("Discovered {$result['discovered']} topic(s), skipped {$result['skipped']} duplicate(s).");

        foreach ($result['errors'] as $error) {
            $this->warn($error);
        }

        return self::SUCCESS;
    }
}
