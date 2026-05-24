<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\SearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(protected SearchService $search) {}

    public function index(Request $request): View
    {
        $searchTerm = trim((string) $request->get('q'));

        if ($searchTerm !== '') {
            $query = $this->search->productQuery($searchTerm);
            if ((clone $query)->count() === 0) {
                $query = $this->search->productQuery($searchTerm, fuzzy: true);
            }
        } else {
            $query = Product::with(['category', 'images'])->where('is_active', true);
        }

        if ($categorySlug = $request->get('category')) {
            $category = Category::where('slug', $categorySlug)->first();
            if ($category) {
                $query->whereIn('category_id', Category::descendantIds($category->id));
            }
        }

        if ($brand = $request->get('brand')) {
            $query->where('brand', $brand);
        }

        if ($request->get('in_stock')) {
            $query->where('in_stock', true)->where('stock_quantity', '>', 0);
        }

        if ($request->boolean('deals')) {
            $query->where(function ($q) {
                $q->whereNotNull('sale_price');
                if (\Illuminate\Support\Facades\Schema::hasColumn('products', 'is_deal')) {
                    $q->orWhere('is_deal', true);
                }
            });
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
        $categories = Category::where('is_active', true)->whereNull('parent_id')->with('children')->orderBy('sort_order')->get();
        $brands = Product::where('is_active', true)->whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand');

        return view('shop.index', compact('products', 'categories', 'brands'));
    }
}
