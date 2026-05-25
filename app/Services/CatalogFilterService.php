<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;

class CatalogFilterService
{
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

        return false;
    }

    public function isCategoryExcluded(Category $category): bool
    {
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

        return false;
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
}
