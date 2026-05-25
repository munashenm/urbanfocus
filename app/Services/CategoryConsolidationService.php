<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;

class CategoryConsolidationService
{
    public function __construct(
        protected CategoryMapperService $mapper,
    ) {}

    /** @return array{products_to_move: int, empty_categories: int, sample_moves: list<array{from: string, to: string, count: int}>} */
    public function preview(): array
    {
        $this->mapper->ensureCanonicalTree();

        $moves = [];
        $productsToMove = 0;

        Product::query()
            ->with('category.parent')
            ->whereNotNull('category_id')
            ->chunkById(200, function ($products) use (&$moves, &$productsToMove) {
                foreach ($products as $product) {
                    $targetId = $this->targetCategoryId($product);

                    if ($targetId === null || $targetId === $product->category_id) {
                        continue;
                    }

                    $productsToMove++;

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

        return [
            'products_to_move' => $productsToMove,
            'empty_categories' => $this->orphanCategoryQuery()->count(),
            'sample_moves' => array_values(array_slice($moves, 0, 12)),
        ];
    }

    /** @return array{moved: int, deactivated: int} */
    public function consolidate(): array
    {
        $this->mapper->ensureCanonicalTree();

        $moved = 0;

        Product::query()
            ->with('category.parent')
            ->whereNotNull('category_id')
            ->chunkById(200, function ($products) use (&$moved) {
                foreach ($products as $product) {
                    $targetId = $this->targetCategoryId($product);

                    if ($targetId === null || $targetId === $product->category_id) {
                        continue;
                    }

                    $product->update(['category_id' => $targetId]);
                    $moved++;
                }
            });

        $deactivated = $this->orphanCategoryQuery()->update(['is_active' => false]);

        return compact('moved', 'deactivated');
    }

    protected function targetCategoryId(Product $product): ?int
    {
        if (! $product->category) {
            return null;
        }

        $parts = $this->categoryPath($product->category);
        $canonical = $this->mapper->mapCategoryParts($parts);

        return $this->mapper->resolveCategoryId($canonical);
    }

    /** @return list<string> */
    protected function categoryPath(Category $category): array
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
            return 'Uncategorized';
        }

        return implode(' > ', $this->categoryPath($category));
    }

    /** @return \Illuminate\Database\Eloquent\Builder<Category> */
    protected function orphanCategoryQuery()
    {
        $canonical = $this->mapper->canonicalSlugs();

        return Category::query()
            ->whereNotIn('slug', $canonical)
            ->whereDoesntHave('products');
    }
}
