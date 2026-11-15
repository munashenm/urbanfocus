<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategorySlugRedirect;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class CategorySlugRedirectCleaner
{
    /**
     * A redirect loops when its target path is the URL the slug already serves:
     * /category/{slug} or /category/{parent}/{slug}.
     */
    public function isSelfReferential(string $oldSlug, string $targetPath, ?Category $category = null): bool
    {
        $slug = $this->normaliseSlug($oldSlug);
        $target = $this->normaliseTarget($targetPath);

        if ($slug === '' || $target === '') {
            return false;
        }

        if ($target === $slug) {
            return true;
        }

        $category ??= Category::query()->with('parent')->where('slug', $oldSlug)->first();

        if (! $category instanceof Category) {
            return false;
        }

        return $target === $this->normaliseTarget($category->urlPath());
    }

    /**
     * @return array<string, true> lower-cased slugs whose redirect points at their own URL
     */
    public function selfReferentialSlugs(): array
    {
        if (! Schema::hasTable('category_slug_redirects')) {
            return [];
        }

        $rows = CategorySlugRedirect::query()->get(['old_slug', 'target_path']);
        if ($rows->isEmpty()) {
            return [];
        }

        $categories = Category::query()
            ->with('parent')
            ->whereIn('slug', $rows->pluck('old_slug')->filter()->unique()->all())
            ->get()
            ->keyBy(fn (Category $category) => strtolower($category->slug));

        $slugs = [];
        foreach ($rows as $row) {
            $category = $categories->get(strtolower((string) $row->old_slug));
            if ($this->isSelfReferential((string) $row->old_slug, (string) $row->target_path, $category)) {
                $slugs[strtolower((string) $row->old_slug)] = true;
            }
        }

        return $slugs;
    }

    public function deleteSelfReferential(): int
    {
        if (! Schema::hasTable('category_slug_redirects')) {
            return 0;
        }

        $slugs = $this->selfReferentialSlugs();
        if ($slugs === []) {
            return 0;
        }

        $deleted = 0;
        CategorySlugRedirect::query()
            ->orderBy('id')
            ->chunkById(100, function (Collection $rows) use (&$deleted, $slugs) {
                foreach ($rows as $row) {
                    if (! isset($slugs[strtolower((string) $row->old_slug)])) {
                        continue;
                    }

                    if ($this->isSelfReferential((string) $row->old_slug, (string) $row->target_path)) {
                        $row->delete();
                        $deleted++;
                    }
                }
            });

        return $deleted;
    }

    public function normaliseTarget(string $targetPath): string
    {
        $targetPath = trim($targetPath);
        if ($targetPath === '') {
            return '';
        }

        if (str_contains($targetPath, '://')) {
            $path = (string) parse_url($targetPath, PHP_URL_PATH);
            $targetPath = preg_replace('#^/?category/#', '', $path) ?? $path;
        }

        return strtolower(trim($targetPath, '/'));
    }

    protected function normaliseSlug(string $slug): string
    {
        return strtolower(trim($slug, '/'));
    }
}
