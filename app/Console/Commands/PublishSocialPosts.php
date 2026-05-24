<?php

namespace App\Console\Commands;

use App\Services\Social\SocialPostingService;
use Illuminate\Console\Command;

class PublishSocialPosts extends Command
{
    protected $signature = 'social:publish {--limit= : Max posts per run}';

    protected $description = 'Publish pending product and blog posts to social media';

    public function handle(SocialPostingService $social): int
    {
        if (! $social->isEnabled()) {
            $this->warn('Social posting is disabled. Set SOCIAL_POSTING_ENABLED=true in .env');

            return self::SUCCESS;
        }

        $result = $social->publishPending(
            $this->option('limit') ? (int) $this->option('limit') : null
        );

        $this->info("Posted: {$result['posted']}, Failed: {$result['failed']}, Skipped: {$result['skipped']}");

        return self::SUCCESS;
    }
}
