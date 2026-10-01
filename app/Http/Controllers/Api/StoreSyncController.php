<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    public function catalogue(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 50), 1), 100);
        $products = Product::query()
            ->with(['category:id,name', 'images'])
            ->orderBy('id')
            ->paginate($perPage);

        return response()->json([
            'page' => $products->currentPage(),
            'perPage' => $products->perPage(),
            'total' => $products->total(),
            'lastPage' => $products->lastPage(),
            'products' => $products->getCollection()->map(fn (Product $product) => $this->catalogueProduct($product))->values(),
        ]);
    }

    public function lookup(Request $request): JsonResponse
    {
        $sku = trim((string) $request->query('sku', ''));
        $mpn = trim((string) $request->query('mpn', ''));
        $barcode = trim((string) $request->query('barcode', ''));
        if ($sku === '' && $mpn === '' && $barcode === '') {
            return response()->json(['error' => 'A SKU, manufacturer part number, or barcode is required.'], 422);
        }

        $product = $sku !== '' ? $this->findBySku($sku) : null;
        if (! $product && $mpn !== '') {
            $product = Product::withTrashed()
                ->whereRaw('LOWER(model_number) = ?', [mb_strtolower($mpn)])
                ->where('model_number', '!=', '')
                ->first();
        }
        if (! $product && $barcode !== '') {
            $product = Product::withTrashed()
                ->whereRaw('LOWER(barcode) = ?', [mb_strtolower($barcode)])
                ->where('barcode', '!=', '')
                ->first();
        }
        if (! $product || trim((string) $product->sku) === '') {
            return response()->json(['sku' => null], 404);
        }

        return response()->json([
            'sku' => $product->sku,
            'storeProductId' => (string) $product->id,
        ]);
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
            'manufacturerPartNumber' => ['nullable', 'string', 'max:191'],
            'barcode' => ['nullable', 'string', 'max:191'],
            'brand' => ['nullable', 'string', 'max:191'],
            'category' => ['nullable', 'string', 'max:191'],
            'seoTitle' => ['nullable', 'string', 'max:191'],
            'metaDescription' => ['nullable', 'string', 'max:500'],
            'shortDescription' => ['nullable', 'string', 'max:500'],
            'slug' => ['nullable', 'string', 'max:191'],
        ]);

        if ($this->findBySku($data['sku'])) {
            return response()->json(['error' => 'A product with this SKU already exists.'], 409);
        }

        $partNumber = trim((string) ($data['manufacturerPartNumber'] ?? ''));
        if ($partNumber !== '') {
            $existing = Product::withTrashed()
                ->whereRaw('LOWER(model_number) = ?', [mb_strtolower($partNumber)])
                ->where('model_number', '!=', '')
                ->first();
            if ($existing) {
                return response()->json(['error' => 'A product with this manufacturer part number already exists.', 'sku' => $existing->sku], 409);
            }
        }

        $barcode = trim((string) ($data['barcode'] ?? ''));
        if ($barcode !== '') {
            $existing = Product::withTrashed()
                ->whereRaw('LOWER(barcode) = ?', [mb_strtolower($barcode)])
                ->where('barcode', '!=', '')
                ->first();
            if ($existing) {
                return response()->json(['error' => 'A product with this barcode already exists.', 'sku' => $existing->sku], 409);
            }
        }

        $categoryName = trim((string) ($data['category'] ?? ''));
        $category = $categoryName === '' ? null : Category::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($categoryName)])->first();
        $requestedSlug = Str::slug((string) ($data['slug'] ?? ''));
        $product = Product::create([
            'sku' => $data['sku'],
            'model_number' => $partNumber !== '' ? $partNumber : null,
            'barcode' => $barcode !== '' ? $barcode : null,
            'brand' => trim((string) ($data['brand'] ?? '')) ?: null,
            'category_id' => $category?->id,
            'name' => $data['name'],
            'slug' => $requestedSlug !== '' && ! Product::withTrashed()->where('slug', $requestedSlug)->exists() ? $requestedSlug : $this->uniqueSlug($data['name']),
            'description' => $data['description'] ?? '',
            'short_description' => Str::limit(trim(strip_tags((string) ($data['description'] ?? ''))), 500, ''),
            'meta_title' => trim((string) ($data['seoTitle'] ?? '')) ?: null,
            'meta_description' => trim((string) ($data['metaDescription'] ?? '')) ?: null,
            'price' => $this->rands($data['unitPriceCents']),
            'sale_price' => null,
            'stock_quantity' => $data['stockQuantity'],
            'manage_stock' => true,
            'in_stock' => $data['stockQuantity'] > 0,
            'is_active' => (bool) $data['published'] && $category !== null,
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
            'seoTitle' => ['nullable', 'string', 'max:180'],
            'metaDescription' => ['nullable', 'string', 'max:300'],
        ]);

        $product->forceFill([
            'name' => $data['name'],
            'description' => $data['description'] ?? '',
            'short_description' => Str::limit(trim(strip_tags((string) ($data['description'] ?? ''))), 500, ''),
            'specifications' => $this->mergedSpecifications($product->specifications ?? [], $data['specifications'] ?? ''),
            'meta_title' => trim((string) ($data['seoTitle'] ?? '')) ?: $product->meta_title,
            'meta_description' => trim((string) ($data['metaDescription'] ?? '')) ?: $product->meta_description,
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

    /**
     * @return array<string, mixed>
     */
    private function catalogueProduct(Product $product): array
    {
        $sale = $this->cents($product->sale_price);
        $price = $this->cents($product->price);

        return [
            'storeProductId' => (string) $product->id,
            'sku' => trim((string) $product->sku),
            'manufacturerPartNumber' => trim((string) $product->model_number),
            'barcode' => trim((string) $product->barcode),
            'name' => (string) $product->name,
            'brand' => trim((string) $product->brand),
            'category' => trim((string) ($product->category?->name ?? '')),
            'unitPriceCents' => $price ?? 0,
            'salePriceCents' => $sale,
            'currency' => 'ZAR',
            'stockQuantity' => max(0, (int) $product->stock_quantity),
            'stockStatus' => $product->in_stock && (int) $product->stock_quantity > 0 ? 'in_stock' : 'out_of_stock',
            'description' => mb_substr(trim(strip_tags((string) $product->description)), 0, 8000),
            'specifications' => $this->specificationText($product->specifications),
            'imageUrls' => $this->catalogueImages($product),
            'published' => (bool) $product->is_active,
            'slug' => (string) $product->slug,
            'url' => $product->slug ? route('products.show', $product) : '',
            'updatedAt' => $product->updated_at?->toIso8601String(),
        ];
    }

    private function cents(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }
        $cents = (int) round(((float) $amount) * 100);
        if ($cents < 0) {
            return null;
        }

        return $cents;
    }

    private function specificationText(mixed $specs): string
    {
        if (! is_array($specs) || $specs === []) {
            return '';
        }
        $lines = [];
        foreach ($specs as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $lines[] = is_int($key) ? (string) $value : $key.': '.$value;
        }

        return mb_substr(implode("\n", $lines), 0, 8000);
    }

    /**
     * @return list<string>
     */
    private function catalogueImages(Product $product): array
    {
        $urls = [];
        foreach ($product->images as $image) {
            if (is_catalog_placeholder_path($image->path)) {
                continue;
            }
            $url = storage_public_url($image->path);
            if (! is_string($url) || (! str_starts_with($url, 'https://') && ! str_starts_with($url, 'http://'))) {
                continue;
            }
            if (! in_array($url, $urls, true)) {
                $urls[] = $url;
            }
            if (count($urls) === 8) {
                break;
            }
        }

        return $urls;
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
        $stored = [];
        foreach ($urls as $url) {
            $path = $this->downloadProductImage($product, trim($url));
            if ($path !== null && ! in_array($path, $stored, true)) {
                $stored[] = $path;
            }
            if (count($stored) === 8) {
                break;
            }
        }

        if ($stored === []) {
            return;
        }

        $product->images()->delete();
        foreach ($stored as $index => $path) {
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'alt_text' => $product->imageAlt(),
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
        }
    }

    private function downloadProductImage(Product $product, string $url): ?string
    {
        if (! str_starts_with($url, 'https://') || str_contains($url, ' ') || strlen($url) > 2000) {
            return null;
        }
        $host = parse_url($url, PHP_URL_HOST);
        if (! is_string($host) || $this->isPrivateHost($host)) {
            return null;
        }
        try {
            $response = Http::timeout(12)->withOptions(['allow_redirects' => false])->get($url);
        } catch (\Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $type = strtolower((string) $response->header('Content-Type'));
        $extension = str_contains($type, 'png') ? 'png' : (str_contains($type, 'webp') ? 'webp' : ((str_contains($type, 'jpeg') || str_contains($type, 'jpg')) ? 'jpg' : ''));
        if ($extension === '') {
            return null;
        }
        $body = $response->body();
        if (strlen($body) < 8000 || strlen($body) > 8000000) {
            return null;
        }
        $path = 'catalog/'.$product->id.'/'.substr(sha1($url), 0, 16).'.'.$extension;
        Storage::disk('public')->put($path, $body);

        return $path;
    }

    private function isPrivateHost(string $host): bool
    {
        $host = strtolower(trim($host, '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            return true;
        }
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
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
