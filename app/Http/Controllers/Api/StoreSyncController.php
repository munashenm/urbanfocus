<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreSyncController extends Controller
{
    public function health(): JsonResponse
    {
        return response()->json(['status' => 'ok']);
    }

    public function findProduct(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));
        if ($sku === '') {
            return response()->json(['error' => 'SKU is required.'], 422);
        }

        $product = $this->findBySku($sku);
        if (! $product) {
            return response()->json(['sku' => null], 404);
        }

        return response()->json(['sku' => $product->sku]);
    }

    public function createProduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sku' => ['required', 'string', 'max:191'],
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:20000'],
            'specifications' => ['nullable', 'string', 'max:8000'],
            'unitPriceCents' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
            'stockQuantity' => ['required', 'integer', 'min:0'],
            'published' => ['required', 'boolean'],
            'imageUrls' => ['nullable', 'array', 'max:8'],
            'imageUrls.*' => ['string', 'max:2000'],
        ]);

        if ($this->findBySku($data['sku'])) {
            return response()->json(['error' => 'A product with this SKU already exists.'], 409);
        }

        $product = Product::create([
            'sku' => $data['sku'],
            'name' => $data['name'],
            'slug' => $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? '',
            'short_description' => Str::limit(trim(strip_tags((string) ($data['description'] ?? ''))), 500, ''),
            'price' => $this->rands($data['unitPriceCents']),
            'sale_price' => null,
            'stock_quantity' => $data['stockQuantity'],
            'manage_stock' => true,
            'in_stock' => $data['stockQuantity'] > 0,
            'is_active' => (bool) $data['published'],
            'specifications' => $this->mergedSpecifications([], $data['specifications'] ?? ''),
        ]);

        $this->replaceImages($product, $data['imageUrls'] ?? []);

        return response()->json(['sku' => $product->sku], 201);
    }

    public function updatePrice(string $sku, Request $request): JsonResponse
    {
        $product = $this->requiredProduct($sku);
        $data = $request->validate([
            'unitPriceCents' => ['required', 'integer', 'min:0'],
            'currency' => ['nullable', 'string', 'max:3'],
        ]);

        $product->forceFill([
            'price' => $this->rands($data['unitPriceCents']),
            'sale_price' => null,
        ])->save();

        return response()->json(['sku' => $product->sku]);
    }

    public function updateStock(string $sku, Request $request): JsonResponse
    {
        $product = $this->requiredProduct($sku);
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'stockStatus' => ['nullable', 'string', 'max:40'],
        ]);

        $product->forceFill([
            'stock_quantity' => $data['quantity'],
            'manage_stock' => true,
            'in_stock' => $data['quantity'] > 0,
        ])->save();

        return response()->json(['sku' => $product->sku]);
    }

    public function updateContent(string $sku, Request $request): JsonResponse
    {
        $product = $this->requiredProduct($sku);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:20000'],
            'specifications' => ['nullable', 'string', 'max:8000'],
        ]);

        $product->forceFill([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'short_description' => Str::limit(trim(strip_tags((string) ($data['description'] ?? ''))), 500, ''),
            'specifications' => $this->mergedSpecifications($product->specifications ?? [], $data['specifications'] ?? ''),
        ])->save();

        return response()->json(['sku' => $product->sku]);
    }

    public function updateImages(string $sku, Request $request): JsonResponse
    {
        $product = $this->requiredProduct($sku);
        $data = $request->validate([
            'imageUrls' => ['required', 'array', 'min:1', 'max:8'],
            'imageUrls.*' => ['string', 'max:2000'],
        ]);

        $this->replaceImages($product, $data['imageUrls']);

        return response()->json(['sku' => $product->sku]);
    }

    public function updatePublication(string $sku, Request $request): JsonResponse
    {
        $product = $this->requiredProduct($sku);
        $data = $request->validate([
            'published' => ['required', 'boolean'],
        ]);

        if ($product->trashed()) {
            $product->restore();
        }

        $product->forceFill(['is_active' => (bool) $data['published']])->save();

        return response()->json(['sku' => $product->sku]);
    }

    public function orders(Request $request): JsonResponse
    {
        $limit = min(max((int) $request->query('limit', 40), 1), 100);
        $orders = Order::query()
            ->with('items')
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Order $order) => [
                'id' => (string) $order->id,
                'number' => $order->order_number,
                'status' => $this->outreachStatus($order->status),
                'email' => $order->customer_email,
                'customerName' => $order->customer_name,
                'companyName' => $order->billing_company ?? '',
                'totalCents' => (int) round(((float) $order->total) * 100),
                'currency' => $order->currency ?: 'ZAR',
                'placedAt' => $order->created_at?->toIso8601String(),
                'lines' => $order->items->map(fn ($item) => [
                    'sku' => $item->product_sku ?: $item->product_name,
                    'quantity' => (int) $item->quantity,
                ])->values(),
            ]);

        return response()->json(['orders' => $orders]);
    }

    private function requiredProduct(string $sku): Product
    {
        $product = $this->findBySku(urldecode($sku));
        abort_if($product === null, 404, 'Product not found.');

        return $product;
    }

    private function findBySku(string $sku): ?Product
    {
        return Product::withTrashed()
            ->whereRaw('LOWER(sku) = ?', [mb_strtolower(trim($sku))])
            ->first();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'product';
        $slug = $base;
        $suffix = 2;
        while (Product::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function rands(int $cents): string
    {
        return number_format($cents / 100, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $current
     * @return array<string, mixed>
     */
    private function mergedSpecifications(array $current, string $incoming): array
    {
        $text = trim($incoming);
        if ($text === '') {
            return $current;
        }

        $current['Details'] = $text;

        return $current;
    }

    /**
     * @param  list<string>  $urls
     */
    private function replaceImages(Product $product, array $urls): void
    {
        $kept = [];
        foreach ($urls as $url) {
            $url = trim($url);
            if (! str_starts_with($url, 'https://') || str_contains($url, ' ')) {
                continue;
            }
            if (! in_array($url, $kept, true)) {
                $kept[] = $url;
            }
            if (count($kept) === 8) {
                break;
            }
        }

        if ($kept === []) {
            return;
        }

        $product->images()->delete();
        foreach ($kept as $index => $url) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $url,
                'alt_text' => $product->imageAlt(),
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    private function outreachStatus(string $status): string
    {
        return match ($status) {
            'pending', 'pending_payment' => 'pending',
            'paid', 'processing', 'awaiting_supplier', 'ready_for_dispatch' => 'processing',
            'delivered', 'completed' => 'completed',
            'cancelled' => 'cancelled',
            'refunded' => 'refunded',
            default => $status,
        };
    }
}
