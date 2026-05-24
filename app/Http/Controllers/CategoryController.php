<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function show(Category $category, Request $request): View
    {
        $category->load(['children', 'parent']);
        $categoryIds = Category::descendantIds($category->id);

        $query = Product::with('images')
            ->whereIn('category_id', $categoryIds)
            ->where('is_active', true);

        if ($brand = $request->get('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->get('in_stock')) {
            $query->where('in_stock', true)->where('stock_quantity', '>', 0);
        }

        if ($min = $request->get('price_min')) {
            $query->whereRaw('COALESCE(sale_price, price) >= ?', [(float) $min]);
        }

        if ($max = $request->get('price_max')) {
            $query->whereRaw('COALESCE(sale_price, price) <= ?', [(float) $max]);
        }

        $sort = $request->get('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) ASC'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) DESC'),
            'name' => $query->orderBy('name'),
            'popular' => $query->orderByDesc('views'),
            default => $query->latest(),
        };

        $products = $query->paginate(24)->withQueryString();

        $brands = Product::whereIn('category_id', $categoryIds)
            ->where('is_active', true)
            ->whereNotNull('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        $siblings = $category->parent_id
            ? Category::where('parent_id', $category->parent_id)->where('is_active', true)->orderBy('sort_order')->get()
            : Category::whereNull('parent_id')->where('is_active', true)->orderBy('sort_order')->get();

        return view('categories.show', compact('category', 'products', 'brands', 'siblings'));
    }
}
