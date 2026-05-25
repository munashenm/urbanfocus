<?php

namespace App\Services;

use App\Models\Category;

class CatalogFilterService
{
    /** @return list<string> */
    public function excludedTerms(): array
    {
        return config('catalog.excluded_category_terms', []);
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

        return false;
    }

    public function isCategoryExcluded(Category $category): bool
    {
        $current = $category;

        while ($current) {
            if ($this->isExcludedName($current->name)) {
                return true;
            }
            $current = $current->parent;
        }

        return false;
    }
}
