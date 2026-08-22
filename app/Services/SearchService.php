<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class SearchService
{
    public function suggest(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            return ['products' => [], 'brands' => [], 'categories' => []];
        }

        $products = $this->productQuery($query)->take($limit)->get();

        if ($products->isEmpty()) {
            $products = $this->productQuery($query, fuzzy: true)->take($limit)->get();
        }

        $brands = Product::where('is_active', true)
            ->whereNotNull('brand')
            ->where('brand', 'like', "%{$query}%")
            ->distinct()
            ->pluck('brand')
            ->take(4)
            ->map(fn ($name) => [
                'name' => $name,
                'url' => route('shop.index', ['brand' => $name]),
            ])
            ->values()
            ->all();

        if (Schema::hasTable('brands')) {
            $dbBrands = Brand::where('is_active', true)
                ->where('name', 'like', "%{$query}%")
                ->take(4)
                ->get()
                ->map(fn (Brand $b) => [
                    'name' => $b->name,
                    'url' => route('brands.show', $b),
                ]);
            $brands = collect($brands)->merge($dbBrands)->unique('name')->take(4)->values()->all();
        }

        $categories = Category::where('is_active', true)
            ->visibleInCatalog()
            ->where('name', 'like', "%{$query}%")
            ->orderBy('sort_order')
            ->take(4)
            ->get()
            ->map(fn (Category $c) => [
                'name' => $c->name,
                'url' => $c->url(),
            ])
            ->all();

        return [
            'products' => $products->map(fn (Product $p) => [
                'name' => $p->name,
                'url' => route('products.show', $p),
                'price' => 'R '.number_format($p->effective_price, 2),
                'brand' => $p->brand,
                'sku' => $p->sku,
                'image' => $p->display_image_url,
                'in_stock' => $p->isAvailable(),
            ])->all(),
            'brands' => $brands,
            'categories' => $categories,
        ];
    }

    public function productQuery(string $search, bool $fuzzy = false): Builder
    {
        $search = trim($search);

        $query = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->withoutDuplicateListings()
            ->when(config('catalog.hide_out_of_stock', true), fn ($q) => $q->availableInStock())
            ->where(function (Builder $q) use ($search, $fuzzy) {
                if ($fuzzy) {
                    $this->applyFuzzySearch($q, $search);
                } else {
                    $this->applySearch($q, $search);
                }
            });

        return $this->applyRelevanceOrder($query, $search);
    }

    public function applyRelevanceOrder(Builder $query, string $search): Builder
    {
        $search = trim($search);

        if ($search === '') {
            return $query;
        }

        $normalized = $this->normalizedCode($search);
        $lower = mb_strtolower($search);
        $prefix = $lower.'%';
        $contains = '%'.$lower.'%';
        $skuExpr = $this->normalizedCodeSql('sku');

        $query->orderByRaw(
            "CASE
                WHEN LOWER(COALESCE(sku,'')) = ? THEN 0
                WHEN {$skuExpr} != '' AND {$skuExpr} = ? THEN 0
                WHEN LOWER(COALESCE(sku,'')) LIKE ? THEN 1
                WHEN {$skuExpr} != '' AND {$skuExpr} LIKE ? THEN 1
                WHEN LOWER(name) LIKE ? THEN 2
                WHEN LOWER(name) LIKE ? THEN 3
                WHEN LOWER(COALESCE(brand,'')) LIKE ? THEN 4
                ELSE 5
            END",
            [
                $lower,
                $normalized,
                $prefix,
                $normalized !== '' ? $normalized.'%' : $prefix,
                $prefix,
                $contains,
                $prefix,
            ]
        )->orderByDesc('views');

        return $query;
    }

    public function applySearch(Builder $query, string $search): Builder
    {
        $terms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [$search];

        foreach ($terms as $term) {
            if (strlen($term) < 2) {
                continue;
            }

            $normalized = $this->normalizedCode($term);

            $query->where(function (Builder $q) use ($term, $normalized) {
                $like = "%{$term}%";
                $q->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('brand', 'like', $like)
                    ->orWhere('model_number', 'like', $like)
                    ->orWhere('short_description', 'like', $like)
                    ->orWhere('barcode', 'like', $like)
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like));

                if (strlen($normalized) >= 3) {
                    $codeLike = "%{$normalized}%";
                    $q->orWhereRaw($this->normalizedCodeSql('sku').' LIKE ?', [$codeLike])
                        ->orWhereRaw($this->normalizedCodeSql('model_number').' LIKE ?', [$codeLike]);
                }
            });
        }

        return $query;
    }

    protected function normalizedCode(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $value));
    }

    protected function normalizedCodeSql(string $column): string
    {
        $safe = in_array($column, ['sku', 'model_number'], true) ? $column : 'sku';

        return "REPLACE(REPLACE(REPLACE(REPLACE(LOWER(COALESCE({$safe},'')), '-', ''), ' ', ''), '/', ''), '_', '')";
    }

    protected function applyFuzzySearch(Builder $query, string $search): void
    {
        $like = '%'.implode('%', str_split(preg_replace('/\s+/', '', $search))).'%';

        $query->where(function (Builder $q) use ($search, $like) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('sku', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('name', 'like', $like)
                ->orWhere('sku', 'like', $like);
        });
    }

    public function megaMenuCategories(): array
    {
        $columns = config('mega-menu.columns', []);
        $dbCategories = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->visibleInCatalog()
            ->with(['children' => fn ($q) => $q->where('is_active', true)->visibleInCatalog()->orderBy('sort_order')])
            ->get()
            ->keyBy('slug');

        return collect($columns)->map(function ($col) use ($dbCategories) {
            $category = $dbCategories->get($col['slug']);

            return [
                'label' => $col['label'],
                'slug' => $col['slug'],
                'icon' => $col['icon'],
                'url' => $category
                    ? $category->url()
                    : route('shop.index', ['category' => $col['slug']]),
                'children' => $category?->children->map(fn ($c) => [
                    'name' => $c->name,
                    'url' => $c->url(),
                ])->all() ?? [],
            ];
        })->all();
    }
}
