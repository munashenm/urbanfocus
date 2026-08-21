<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogDeduper;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View|RedirectResponse
    {
        abort_unless($product->is_active, 404);

        $canonical = app(CatalogDeduper::class)->canonicalProduct($product);
        if ($canonical->id !== $product->id) {
            return redirect()->route('products.show', $canonical, 301);
        }

        $product->increment('views');
        $product->load(['category', 'images']);

        $recentIds = collect(session('recently_viewed', []))
            ->prepend($product->id)
            ->unique()
            ->take(9)
            ->values()
            ->all();
        session(['recently_viewed' => $recentIds]);

        $relatedProducts = Product::with('images')
            ->forStorefront()
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        $accessories = Product::with('images')
            ->forStorefront()
            ->where('id', '!=', $product->id)
            ->where(function ($q) use ($product) {
                $peripheral = Category::where('slug', 'peripherals')->first();
                if ($peripheral) {
                    $q->where('category_id', $peripheral->id);
                }
                if ($product->brand) {
                    $q->orWhere('brand', $product->brand);
                }
            })
            ->take(4)
            ->get();

        $recentlyViewed = Product::with('images')
            ->forStorefront()
            ->whereIn('id', array_slice($recentIds, 1))
            ->get()
            ->sortBy(fn (Product $item) => array_search($item->id, $recentIds, true))
            ->values();

        $schema = $product->toSchemaArray();
        $breadcrumbSchema = $product->toBreadcrumbSchema();

        return view('products.show', compact(
            'product', 'relatedProducts', 'accessories', 'recentlyViewed', 'schema', 'breadcrumbSchema'
        ));
    }
}
