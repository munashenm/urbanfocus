<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class CatalogBrowseService
{
    public function __construct(
        protected SearchService $search,
        protected CategoryMapperService $categories,
    ) {}

    public function defaultSort(Request $request): string
    {
        if (trim((string) $request->get('q')) !== '') {
            return (string) config('catalog.default_search_sort', 'relevance');
        }

        return (string) config('catalog.default_sort', 'recommended');
    }

    public function requestedSort(Request $request): string
    {
        $requested = trim((string) $request->get('sort'));

        return $requested !== '' ? $requested : $this->defaultSort($request);
    }

    public function isDefaultSort(Request $request): bool
    {
        $requested = trim((string) $request->get('sort'));

        return $requested === '' || $requested === $this->defaultSort($request);
    }

    public function applySort(Builder $query, string $sort, ?string $search = null): void
    {
        $query->reorder();

        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) DESC'),
            'name' => $query->orderBy('name'),
            'popular' => $query->orderByDesc('views')->orderByDesc('created_at'),
            'newest' => $query->latest(),
            'relevance' => $this->search->applyRelevanceOrder($query, (string) $search),
            default => $this->applyRecommendedOrder($query),
        };
    }

    public function applyRecommendedOrder(Builder $query): void
    {
        [$sql, $bindings] = $this->recommendedOrderSql();

        $query->orderByRaw($sql, $bindings)
            ->orderByDesc('views')
            ->orderByDesc('created_at');
    }

    /** @return array{0: string, 1: list<mixed>} */
    protected function recommendedOrderSql(): array
    {
        $sql = 'CASE ';
        $bindings = [];

        $sql .= $this->nameTermsWhen(config('catalog.browse_demote_name_terms', []), 8, $bindings);
        $sql .= $this->nameTermsWhen(config('catalog.browse_hero_name_terms', []), 0, $bindings);
        $sql .= $this->nameTermsWhen(config('catalog.browse_accessory_name_terms', []), 5, $bindings);

        $priorityIds = $this->priorityCategoryIds();
        if ($priorityIds !== []) {
            $placeholders = implode(',', array_fill(0, count($priorityIds), '?'));
            $sql .= "WHEN category_id IN ({$placeholders}) THEN 1 ";
            array_push($bindings, ...$priorityIds);
        }

        $sql .= 'ELSE 4 END';

        return [$sql, $bindings];
    }

    /**
     * @param  list<string>  $terms
     * @param  list<mixed>  $bindings
     */
    protected function nameTermsWhen(array $terms, int $rank, array &$bindings): string
    {
        $parts = [];

        foreach ($terms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }

            $parts[] = 'LOWER(name) LIKE ?';
            $bindings[] = '%'.mb_strtolower($term).'%';
        }

        if ($parts === []) {
            return '';
        }

        return 'WHEN ('.implode(' OR ', $parts).') THEN '.$rank.' ';
    }

    /** @return list<int> */
    protected function priorityCategoryIds(): array
    {
        $ids = [];

        foreach (config('catalog.priority_category_paths', []) as $path) {
            $category = $this->categories->resolveCategoryForFilter((string) $path);
            if (! $category) {
                continue;
            }

            foreach (Category::descendantIds($category->id) as $id) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
