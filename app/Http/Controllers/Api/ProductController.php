<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::with(['category', 'images'])
            ->where('is_active', true);

        if ($search = $request->get('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('in_stock')) {
            $query->where('in_stock', true)->where('stock_quantity', '>', 0);
        }

        $products = $query->latest()->paginate(min((int) $request->get('per_page', 50), 100));

        return response()->json([
            'data' => $products->getCollection()->map(fn (Product $p) => $this->transform($p)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
        ]);
    }

    public function show(string $identifier): JsonResponse
    {
        $product = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where(function ($q) use ($identifier) {
                $q->where('slug', $identifier)
                    ->orWhere('sku', $identifier)
                    ->orWhere('id', $identifier);
            })
            ->firstOrFail();

        return response()->json(['data' => $this->transform($product)]);
    }

    protected function transform(Product $product): array
    {
        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'slug' => $product->slug,
            'url' => route('products.show', $product),
            'brand' => $product->brand,
            'category' => $product->category?->name,
            'price' => (float) $product->price,
            'sale_price' => $product->sale_price ? (float) $product->sale_price : null,
            'effective_price' => $product->effective_price,
            'currency' => 'ZAR',
            'in_stock' => $product->isAvailable(),
            'stock_quantity' => $product->stock_quantity,
            'short_description' => $product->short_description,
            'description' => strip_tags($product->description ?? ''),
            'image' => $product->primary_image_url,
            'images' => $product->images->map(fn ($img) => storage_public_url($img->path))->values(),
            'meta_title' => $product->getAttributes()['meta_title'] ?? null,
            'meta_description' => $product->getAttributes()['meta_description'] ?? null,
        ];
    }
}
