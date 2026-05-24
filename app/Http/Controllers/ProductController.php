<?php

namespace App\Http\Controllers;

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
            ->where('is_active', true)
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->take(4)
            ->get();

        $schema = $product->toSchemaArray();
        $breadcrumbSchema = $product->toBreadcrumbSchema();

        return view('products.show', compact('product', 'relatedProducts', 'schema', 'breadcrumbSchema'));
    }
}
