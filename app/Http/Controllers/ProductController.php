<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Product $product): View
    {
        abort_unless($product->is_active, 404);

        $product->increment('views');
        $product->load(['category', 'images']);

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

        $schema = $product->toSchemaArray();
        $breadcrumbSchema = $product->toBreadcrumbSchema();

        return view('products.show', compact('product', 'relatedProducts', 'accessories', 'schema', 'breadcrumbSchema'));
    }
}
