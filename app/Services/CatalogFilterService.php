<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Collection;

class CatalogFilterService
{
    /** @var list<int>|null */
    protected ?array $excludedCategoryIdsCache = null;

    /** @return list<string> */
    public function itCategoryHeads(): array
    {
        return config('catalog.it_category_heads', []);
    }

    /** @return list<string> */
    public function itCategoryExceptions(): array
    {
        return config('catalog.it_category_exceptions', []);
    }

    /** @return list<string> */
    public function excludedTerms(): array
    {
        return config('catalog.excluded_category_terms', []);
    }

    /** @return list<string> */
    public function excludedProductTerms(): array
    {
        return array_values(array_unique(array_merge(
            $this->excludedTerms(),
            config('catalog.excluded_product_terms', []),
        )));
    }

    public function textMatchesExcludedTerms(string $text): bool
    {
        $text = strtolower(trim($text));

        if ($text === '') {
            return false;
        }

        foreach ($this->excludedProductTerms() as $term) {
            $term = strtolower(trim($term));
            if ($term !== '' && str_contains($text, $term)) {
                return true;
            }
        }

        return false;
    }

    public function isExcludedName(string $name): bool
    {
        $name = strtolower(trim($name));

        if ($name === '') {
            return false;
        }

        foreach ($this->excludedTerms() as $term) {
            $term = strtolower(trim($term));
            if ($term !== '' && str_contains($name, $term)) {
                return true;
            }
        }

        return false;
    }

    public function isItCategoryHead(string $name): bool
    {
        $name = strtolower(trim($name));

        foreach ($this->itCategoryHeads() as $head) {
            if (strtolower(trim($head)) === $name) {
                return true;
            }
        }

        return false;
    }

    public function isItCategoryException(Category $category): bool
    {
        $name = strtolower(trim($category->name));

        foreach ($this->itCategoryExceptions() as $exception) {
            if (strtolower(trim($exception)) === $name) {
                return true;
            }
        }

        return false;
    }

    public function resolveRootCategory(Category $category): Category
    {
        $current = $category;
        $seen = [];

        while ($current->parent_id) {
            if (in_array($current->id, $seen, true)) {
                break;
            }

            $seen[] = $current->id;

            if (! $current->relationLoaded('parent')) {
                $current->load('parent');
            }

            $current = $current->parent;

            if (! $current) {
                break;
            }
        }

        return $current;
    }

    public function isExcludedCategoryPath(string $path): bool
    {
        foreach (preg_split('/\s*>\s*/', $path) ?: [] as $segment) {
            if ($this->isExcludedName($segment)) {
                return true;
            }
        }

        return false;
    }

    public function isExcludedImportRow(array $data): bool
    {
        $categoryHead = trim($data['category_head'] ?? '');

        if ($categoryHead !== '' && ! $this->isItCategoryHead($categoryHead)) {
            return true;
        }

        $segments = array_filter([
            $data['category_head'] ?? null,
            $data['category'] ?? null,
        ]);

        if ($segments !== []) {
            $path = implode(' > ', $segments);
            if ($this->isExcludedCategoryPath($path)) {
                return true;
            }
        }

        if (! empty($data['categories']) && $this->isExcludedCategoryPath($data['categories'])) {
            return true;
        }

        $productText = trim(implode(' ', array_filter([
            $data['name'] ?? null,
            $data['short_description'] ?? null,
        ])));

        if ($productText !== '' && $this->textMatchesExcludedTerms($productText)) {
            return true;
        }

        if ($categoryHead === '' && trim($data['category'] ?? '') !== '') {
            $categoryName = trim($data['category']);
            if (! $this->isItCategoryExceptionName($categoryName) && ! $this->isItCategoryHead($categoryName)) {
                return true;
            }
        }

        return false;
    }

    public function isItCategoryExceptionName(string $name): bool
    {
        $name = strtolower(trim($name));

        foreach ($this->itCategoryExceptions() as $exception) {
            if (strtolower(trim($exception)) === $name) {
                return true;
            }
        }

        return false;
    }

    public function isCategoryExcluded(Category $category): bool
    {
        if ($this->isItCategoryException($category)) {
            return false;
        }

        $current = $category;
        $seen = [];

        while ($current) {
            if (in_array($current->id, $seen, true)) {
                break;
            }

            $seen[] = $current->id;

            if ($this->isExcludedName($current->name)) {
                return true;
            }

            if (! $current->relationLoaded('parent') && $current->parent_id) {
                $current->load('parent');
            }

            $current = $current->parent;
        }

        $root = $this->resolveRootCategory($category);

        if ($this->isItCategoryHead($root->name)) {
            return false;
        }

        return true;
    }

    public function isProductNameExcluded(Product $product): bool
    {
        $text = trim(implode(' ', array_filter([
            $product->name,
            $product->short_description,
        ])));

        return $this->textMatchesExcludedTerms($text);
    }

    public function isProductExcluded(Product $product): bool
    {
        $category = $product->relationLoaded('category')
            ? $product->category
            : $product->category()->first();

        if ($category && $this->isCategoryExcluded($category)) {
            return true;
        }

        return $this->isProductNameExcluded($product);
    }

    /** @return list<int> */
    public function excludedCategoryIds(): array
    {
        if ($this->excludedCategoryIdsCache !== null) {
            return $this->excludedCategoryIdsCache;
        }

        $this->excludedCategoryIdsCache = $this->collectExcludedCategoryIds();

        return $this->excludedCategoryIdsCache;
    }

    /** @return list<int> */
    public function collectExcludedCategoryIds(): array
    {
        return Category::query()
            ->get()
            ->filter(fn (Category $category) => $this->isCategoryExcluded($category))
            ->pluck('id')
            ->values()
            ->all();
    }

    /** @return Collection<int, Category> */
    public function collectExcludedCategories(): Collection
    {
        return Category::query()
            ->get()
            ->filter(fn (Category $category) => $this->isCategoryExcluded($category))
            ->values();
    }

    /** @param \Illuminate\Support\Collection<int, Category> $categories */
    public function filterVisibleCategories(\Illuminate\Support\Collection $categories): \Illuminate\Support\Collection
    {
        $excluded = array_flip($this->excludedCategoryIds());

        return $categories
            ->reject(fn (Category $category) => isset($excluded[$category->id]))
            ->values();
    }
}
