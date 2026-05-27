<?php

namespace App\Console\Commands;

use Database\Seeders\BlogSeeder;
use Illuminate\Console\Command;

class SeedBlogPillars extends Command
{
    protected $signature = 'blog:seed-pillars';

    protected $description = 'Seed or update SEO pillar articles on the Urban Focus blog';

    public function handle(): int
    {
        $this->call(BlogSeeder::class);

        $this->info('Blog pillar articles seeded. Visit /blog to review.');

        return self::SUCCESS;
    }
}
