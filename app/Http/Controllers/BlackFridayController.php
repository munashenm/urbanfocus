<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\BlackFridaySpecialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BlackFridayController extends Controller
{
    public function index(Request $request): View
    {
        $categorySlug = trim((string) $request->query('category', ''));
        $category = $categorySlug !== ''
            ? Category::query()->where('slug', $categorySlug)->where('is_active', true)->first()
            : null;

        $products = Product::query()
            ->with(['images', 'category'])
            ->forStorefront(true)
            ->tap(fn ($query) => app(BlackFridaySpecialService::class)->constrainToSpecials($query))
            ->when($category, function ($query) use ($category) {
                $query->whereIn('category_id', Category::descendantIds($category->id));
            })
            ->reorder()
            ->orderByRaw('CASE WHEN (manage_stock = 1 AND stock_quantity > 0) OR (manage_stock = 0 AND in_stock = 1) THEN 0 ELSE 1 END')
            ->tap(fn ($query) => app(BlackFridaySpecialService::class)->orderPinnedFirst($query))
            ->tap(function ($query) use ($request) {
                $now = now()->toDateTimeString();
                $sort = (string) $request->query('sort', 'saving');
                if ($sort === 'price_asc') {
                    $query->orderByRaw(Product::effectivePriceSql().' ASC', [$now, $now]);
                } elseif ($sort === 'price_desc') {
                    $query->orderByRaw(Product::effectivePriceSql().' DESC', [$now, $now]);
                } else {
                    $query->orderByRaw('((price - sale_price) / price) DESC');
                }
            })
            ->paginate(24)
            ->withQueryString();

        $categories = Category::query()
            ->where('is_active', true)
            ->whereIn('id', Product::query()->where('is_active', true)->tap(fn ($query) => app(BlackFridaySpecialService::class)->constrainToSpecials($query))->whereNotNull('category_id')->distinct()->pluck('category_id'))
            ->orderBy('name')
            ->get();

        return view('black-friday.index', [
            'products' => $products,
            'categories' => $categories,
            'activeCategory' => $category,
        ]);
    }
}
