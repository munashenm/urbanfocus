<?php

namespace App\Services;

use App\Models\Category;
use App\Models\CategoryMigrationBackup;
use App\Models\CategoryMigrationMapping;
use App\Models\CategorySlugRedirect;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CategoryReorganizationService
{
    public function __construct(
        protected CategoryMapperService $mapper,
    ) {}

    /** @return array<string, mixed> */
    public function preview(int $sampleLimit = 20): array
    {
        $this->mapper->ensureCanonicalTree();

        $canonicalIds = $this->canonicalCategoryIds();
        $moves = [];
        $unchanged = 0;
        $uncategorized = 0;

        Product::query()
            ->with('category.parent')
            ->orderBy('id')
            ->chunkById(200, function ($products) use ($canonicalIds, &$moves, &$unchanged, &$uncategorized) {
                foreach ($products as $product) {
                    if (! $product->category_id) {
                        $uncategorized++;

                        continue;
                    }

                    $targetId = $this->resolveTargetCategoryId($product->category);

                    if ($targetId === null) {
                        $uncategorized++;

                        continue;
                    }

                    if ($targetId === $product->category_id) {
                        $unchanged++;

                        continue;
                    }

                    $from = $this->categoryLabel($product->category);
                    $to = $this->categoryLabel(Category::find($targetId));
                    $key = $from.' → '.$to;

                    if (! isset($moves[$key])) {
                        $moves[$key] = ['from' => $from, 'to' => $to, 'count' => 0];
                    }

                    $moves[$key]['count']++;
                }
            });

        uasort($moves, fn (array $a, array $b) => $b['count'] <=> $a['count']);

        $orphans = Category::query()
            ->whereNotIn('id', $canonicalIds)
            ->withCount('products')
            ->orderByDesc('products_count')
            ->get()
            ->map(fn (Category $c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'products' => $c->products_count,
                'suggested' => $this->pathLabel($this->slugPathForCategory($c)),
            ]);

        return [
            'products_to_move' => array_sum(array_column($moves, 'count')),
            'products_unchanged' => $unchanged,
            'products_uncategorized' => $uncategorized,
            'sample_moves' => array_values(array_slice($moves, 0, $sampleLimit)),
            'orphan_categories' => $orphans->take(30)->values()->all(),
            'redirects_to_create' => $this->redirectPreviewCount(),
            'main_categories' => count(config('category_tree.tree', [])),
        ];
    }

    /** @return array<string, mixed> */
    public function reorganize(bool $backup = true, ?int $limit = null): array
    {
        return DB::transaction(function () use ($backup, $limit) {
            if ($backup) {
                $this->backupProductCategories();
            }

            $batch = $this->remapProducts(limit: $limit);

            $this->recordCategoryMappings($batch['mapping_counts']);
            $redirects = $this->buildSlugRedirects();
            $deactivated = $this->deactivateOrphanCategories();

            return [
                'moved' => $batch['moved'],
                'processed' => $batch['processed'],
                'redirects' => $redirects,
                'deactivated' => $deactivated,
            ];
        });
    }

    /**
     * Remap a slice of products for cPanel batch runs (no transaction wrapper).
     *
     * @return array{moved: int, processed: int, mapping_counts: array<int, int>, has_more: bool, next_offset: int, total: int}
     */
    public function remapProductBatch(int $offset, int $batchSize, bool $backupOnFirst = false): array
    {
        if ($backupOnFirst && $offset === 0) {
            $this->backupProductCategories();
        }

        $this->mapper->ensureCanonicalTree();

        $batch = $this->remapProducts(offset: $offset, limit: $batchSize);
        $total = Product::query()->whereNotNull('category_id')->count();
        $nextOffset = $offset + $batch['processed'];
        $hasMore = $nextOffset < $total;

        return array_merge($batch, [
            'has_more' => $hasMore,
            'next_offset' => $nextOffset,
            'total' => $total,
        ]);
    }

    /** @return array{redirects: int, deactivated: int} */
    public function finalizeMigration(): array
    {
        return [
            'redirects' => $this->buildSlugRedirects(),
            'deactivated' => $this->deactivateOrphanCategories(),
        ];
    }

    public function migrationTablesReady(): bool
    {
        return \Illuminate\Support\Facades\Schema::hasTable('category_slug_redirects')
            && \Illuminate\Support\Facades\Schema::hasTable('category_migration_mappings')
            && \Illuminate\Support\Facades\Schema::hasTable('category_migration_backups');
    }

    /**
     * @return array{moved: int, processed: int, mapping_counts: array<int, int>}
     */
    protected function remapProducts(?int $offset = null, ?int $limit = null): array
    {
        $moved = 0;
        $processed = 0;
        $mappingCounts = [];

        $query = Product::query()->with('category.parent')->whereNotNull('category_id')->orderBy('id');

        if ($offset !== null) {
            $query->offset($offset);
        }

        if ($limit !== null) {
            $query->limit($limit);
        }

        foreach ($query->get() as $product) {
            $processed++;
            $oldCategory = $product->category;

            if (! $oldCategory) {
                continue;
            }

            $targetId = $this->resolveTargetCategoryId($oldCategory);

            if ($targetId === null || $targetId === $product->category_id) {
                continue;
            }

            $product->update(['category_id' => $targetId]);
            $moved++;

            $key = $oldCategory->id;
            $mappingCounts[$key] = ($mappingCounts[$key] ?? 0) + 1;
        }

        return [
            'moved' => $moved,
            'processed' => $processed,
            'mapping_counts' => $mappingCounts,
        ];
    }

    protected function backupProductCategories(): void
    {
        CategoryMigrationBackup::query()->delete();

        Product::query()
            ->whereNotNull('category_id')
            ->with('category')
            ->chunkById(500, function ($products) {
                $rows = [];

                foreach ($products as $product) {
                    $rows[] = [
                        'product_id' => $product->id,
                        'old_category_id' => $product->category_id,
                        'old_category_slug' => $product->category?->slug,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($rows !== []) {
                    CategoryMigrationBackup::insert($rows);
                }
            });
    }

    /** @param array<int, int> $mappingCounts */
    protected function recordCategoryMappings(array $mappingCounts): void
    {
        CategoryMigrationMapping::query()->delete();

        foreach ($mappingCounts as $oldCategoryId => $count) {
            $old = Category::find($oldCategoryId);

            if (! $old) {
                continue;
            }

            $targetId = $this->resolveTargetCategoryId($old);
            $path = $this->slugPathForCategory($old);

            CategoryMigrationMapping::create([
                'old_category_id' => $old->id,
                'new_category_id' => $targetId,
                'old_slug' => $old->slug,
                'new_slug_path' => $path,
                'match_method' => $this->matchMethodForCategory($old),
                'products_moved' => $count,
            ]);
        }
    }

    protected function buildSlugRedirects(): int
    {
        $created = 0;
        $canonicalSlugs = $this->mapper->canonicalSlugs();

        Category::query()->with('parent')->chunkById(200, function ($categories) use ($canonicalSlugs, &$created) {
            foreach ($categories as $category) {
                $targetPath = $category->urlPath();

                if ($category->parent_id) {
                    $targetPath = $category->parent->slug.'/'.$category->slug;
                }

                if (in_array($category->slug, $canonicalSlugs, true) && ! $category->parent_id) {
                    continue;
                }

                if ($category->parent_id) {
                    $canonicalChild = Category::where('slug', $category->slug)
                        ->whereHas('parent', fn ($q) => $q->where('slug', $category->parent->slug))
                        ->whereIn('slug', $canonicalSlugs)
                        ->exists();

                    if ($canonicalChild) {
                        continue;
                    }
                }

                $existing = CategorySlugRedirect::where('old_slug', $category->slug)->first();

                if ($existing) {
                    $existing->update(['target_path' => $targetPath]);

                    continue;
                }

                if ($this->redirectTargetExists($targetPath)) {
                    CategorySlugRedirect::create([
                        'old_slug' => $category->slug,
                        'target_path' => $targetPath,
                    ]);
                    $created++;
                }
            }
        });

        foreach (config('category_tree.legacy_slug_map', []) as $oldSlug => $newPath) {
            if (CategorySlugRedirect::where('old_slug', $oldSlug)->exists()) {
                continue;
            }

            if ($this->redirectTargetExists($newPath)) {
                CategorySlugRedirect::create([
                    'old_slug' => $oldSlug,
                    'target_path' => str_contains($newPath, '/')
                        ? $newPath
                        : $newPath,
                ]);
                $created++;
            }
        }

        return $created;
    }

    protected function redirectTargetExists(string $path): bool
    {
        [$parentSlug, $childSlug] = array_pad(explode('/', $path, 2), 2, null);
        $parent = Category::where('slug', $parentSlug)->whereNull('parent_id')->first();

        if (! $parent) {
            return false;
        }

        if ($childSlug) {
            return Category::where('slug', $childSlug)->where('parent_id', $parent->id)->exists();
        }

        return true;
    }

    protected function deactivateOrphanCategories(): int
    {
        $canonicalIds = $this->canonicalCategoryIds();

        return Category::query()
            ->whereNotIn('id', $canonicalIds)
            ->whereDoesntHave('products')
            ->update(['is_active' => false]);
    }

    /** @return list<int> */
    protected function canonicalCategoryIds(): array
    {
        $slugs = $this->mapper->canonicalSlugs();

        return Category::whereIn('slug', $slugs)->pluck('id')->all();
    }

    protected function resolveTargetCategoryId(Category $category): ?int
    {
        $slugPath = $this->slugPathForCategory($category);

        if ($slugPath) {
            $resolved = $this->mapper->resolveCategoryIdFromPath($slugPath);

            if ($resolved) {
                return $resolved;
            }
        }

        $parts = $this->categoryPathNames($category);
        $mapped = $this->mapper->mapCategoryParts($parts);
        $fromParts = $this->mapper->resolveCategoryId($mapped);

        if ($fromParts) {
            return $fromParts;
        }

        return $this->fallbackMainCategoryId($category);
    }

    protected function slugPathForCategory(Category $category): ?string
    {
        $legacy = config('category_tree.legacy_slug_map', []);

        if (isset($legacy[$category->slug])) {
            return $legacy[$category->slug];
        }

        if ($category->parent_id) {
            $parent = $category->parent ?? Category::find($category->parent_id);

            if ($parent && isset($legacy[$parent->slug])) {
                return $legacy[$parent->slug];
            }
        }

        foreach ($this->canonicalSlugPaths() as $path) {
            [, $childSlug] = array_pad(explode('/', $path, 2), 2, null);

            if ($childSlug === $category->slug) {
                return $path;
            }
        }

        if (! $category->parent_id && $this->mainCategorySlugExists($category->slug)) {
            $firstChild = $this->firstChildPathForParent($category->slug);

            return $firstChild ?? $category->slug;
        }

        if ($category->parent_id && $this->childExistsInTree($category)) {
            return $category->parent->slug.'/'.$category->slug;
        }

        return null;
    }

    protected function matchMethodForCategory(Category $category): string
    {
        if (isset(config('category_tree.legacy_slug_map', [])[$category->slug])) {
            return 'legacy_slug_map';
        }

        if ($this->childExistsInTree($category)) {
            return 'canonical_match';
        }

        return 'fallback_main';
    }

    protected function childExistsInTree(Category $category): bool
    {
        if (! $category->parent_id) {
            return $this->mainCategorySlugExists($category->slug);
        }

        $parent = $category->parent;

        if (! $parent) {
            return false;
        }

        foreach (config('category_tree.tree', []) as $parentData) {
            if ($parentData['slug'] !== $parent->slug) {
                continue;
            }

            foreach ($parentData['children'] ?? [] as $child) {
                if ($child['slug'] === $category->slug) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function mainCategorySlugExists(string $slug): bool
    {
        foreach (config('category_tree.tree', []) as $parent) {
            if ($parent['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    protected function firstChildPathForParent(string $parentSlug): ?string
    {
        foreach (config('category_tree.tree', []) as $parent) {
            if ($parent['slug'] !== $parentSlug) {
                continue;
            }

            $child = $parent['children'][0] ?? null;

            return $child ? $parentSlug.'/'.$child['slug'] : null;
        }

        return null;
    }

    /** @return list<string> */
    protected function canonicalSlugPaths(): array
    {
        $paths = [];

        foreach (config('category_tree.tree', []) as $parent) {
            $paths[] = $parent['slug'];
            foreach ($parent['children'] ?? [] as $child) {
                $paths[] = $parent['slug'].'/'.$child['slug'];
            }
        }

        return $paths;
    }

    protected function fallbackMainCategoryId(Category $category): ?int
    {
        $name = strtolower($category->name.' '.($category->parent?->name ?? ''));

        $rules = [
            'security-surveillance' => '/\b(cctv|camera|nvr|dvr|alarm|access|biometric|intercom|lock|surveillance|security|dash|fleet|gps|mdvr|vehicle|body worn|helmet|driver|passenger)\b/i',
            'networking-connectivity' => '/\b(network|router|switch|access point|fibre|fiber|poe|wireless|starlink)\b/i',
            'solar-power' => '/\b(solar|inverter|battery|ups|power|backup)\b/i',
            'digital-signage' => '/\b(signage|display|led|kiosk|media player|taxi screen|bus)\b/i',
            'gaming-entertainment' => '/\b(gaming|console|stream)\b/i',
            'smart-solutions' => '/\b(smart|iot|sensor|municipality|agriculture)\b/i',
            'business-retail' => '/\b(pos|barcode|receipt|retail|cash drawer|queue|attendance)\b/i',
            'industrial-commercial' => '/\b(warehouse|industrial|cold chain|worker safety|asset track)\b/i',
        ];

        foreach ($rules as $parentSlug => $pattern) {
            if (preg_match($pattern, $name)) {
                $path = $this->firstChildPathForParent($parentSlug) ?? $parentSlug;

                return $this->mapper->resolveCategoryIdFromPath($path);
            }
        }

        return $this->mapper->resolveCategoryIdFromPath('computing-office/computer-accessories');
    }

    /** @return list<string> */
    protected function categoryPathNames(Category $category): array
    {
        $parts = [];
        $current = $category;

        while ($current) {
            array_unshift($parts, $current->name);
            $current = $current->parent;
        }

        return $parts;
    }

    protected function categoryLabel(?Category $category): string
    {
        if (! $category) {
            return 'Uncategorised';
        }

        return implode(' > ', $this->categoryPathNames($category));
    }

    protected function pathLabel(?string $path): string
    {
        if (! $path) {
            return '—';
        }

        return str_replace('/', ' > ', $path);
    }

    protected function redirectPreviewCount(): int
    {
        return count(config('category_tree.legacy_slug_map', []));
    }
}
