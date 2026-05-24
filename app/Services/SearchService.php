<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;

class SearchService
{
    public function suggest(string $query, int $limit = 8): array
    {
        $query = trim($query);

        if (strlen($query) < 2) {
            return [];
        }

        $products = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('sku', 'like', "%{$query}%")
                    ->orWhere('brand', 'like', "%{$query}%")
                    ->orWhere('model_number', 'like', "%{$query}%")
                    ->orWhere('short_description', 'like', "%{$query}%")
                    ->orWhereHas('category', fn ($c) => $c->where('name', 'like', "%{$query}%"));
            })
            ->orderByRaw('CASE WHEN name LIKE ? THEN 0 WHEN sku LIKE ? THEN 1 ELSE 2 END', ["{$query}%", "{$query}%"])
            ->take($limit)
            ->get();

        return $products->map(fn (Product $p) => [
            'name' => $p->name,
            'url' => route('products.show', $p),
            'price' => 'R '.number_format($p->effective_price, 2),
            'brand' => $p->brand,
            'sku' => $p->sku,
            'image' => $p->primary_image_url,
            'in_stock' => $p->isAvailable(),
        ])->all();
    }

    public function megaMenuCategories(): array
    {
        $columns = config('mega-menu.columns', []);
        $dbCategories = Category::where('is_active', true)
            ->with(['children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->get()
            ->keyBy('slug');

        return collect($columns)->map(function ($col) use ($dbCategories) {
            $category = $dbCategories->get($col['slug']);

            return [
                'label' => $col['label'],
                'slug' => $col['slug'],
                'icon' => $col['icon'],
                'url' => $category
                    ? route('categories.show', $category)
                    : route('shop.index', ['category' => $col['slug']]),
                'children' => $category?->children->map(fn ($c) => [
                    'name' => $c->name,
                    'url' => route('categories.show', $c),
                ])->all() ?? [],
            ];
        })->all();
    }
}
