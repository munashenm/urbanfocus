<?php

namespace App\Console\Commands;

use App\Services\CategorySlugRedirectCleaner;
use Illuminate\Console\Command;

class CleanCategorySlugRedirects extends Command
{
    protected $signature = 'categories:clean-slug-redirects
                            {--dry-run : List self-referential redirects without deleting them}';

    protected $description = 'Delete category slug redirects that point at the same URL (301 loops)';

    public function handle(CategorySlugRedirectCleaner $cleaner): int
    {
        $slugs = array_keys($cleaner->selfReferentialSlugs());

        if ($slugs === []) {
            $this->info('No self-referential category redirects.');

            return self::SUCCESS;
        }

        $this->table(['Old slug'], array_map(fn (string $slug) => [$slug], $slugs));

        if ($this->option('dry-run')) {
            $this->comment(count($slugs).' self-redirect(s). Re-run without --dry-run to delete them.');

            return self::SUCCESS;
        }

        $deleted = $cleaner->deleteSelfReferential();
        $this->info("Deleted {$deleted} self-referential category redirect(s).");

        return self::SUCCESS;
    }
}
