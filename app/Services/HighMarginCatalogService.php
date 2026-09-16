<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Social\SocialPostingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class HighMarginCatalogService
{
    public const CATALOG_RANGE_SPEC_KEY = 'Urban Focus range';

    public const CATALOG_RANGE_SPEC_VALUE = 'High-margin technology';

    public const LISTING_PHOTO_SPEC_KEY = 'Listing photo';

    public function __construct(
        protected CategoryMapperService $categories,
        protected ProductPricingService $pricing,
        protected CatalogDeduper $deduper,
        protected ImageService $images,
        protected HighMarginListingCopy $copy,
    ) {}

    public function catalogPath(): string
    {
        return (string) config('catalog.high_margin_path', database_path('data/high-margin-products.php'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(?string $path = null): array
    {
        $path ??= $this->catalogPath();

        if (! is_readable($path)) {
            throw new \RuntimeException('High-margin catalog file is not readable: '.$path);
        }

        $decoded = str_ends_with(strtolower($path), '.php')
            ? require $path
            : json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('High-margin catalog file is not valid: '.$path);
        }

        return array_values(array_filter($decoded, fn ($item) => is_array($item) && ! empty($item['sku']) && ! empty($item['name'])));
    }

    /**
     * Exact VAT-inclusive retail. No specialist 15% top-up.
     *
     * @param  array<string, mixed>  $item
     */
    public function retailPrice(array $item): float
    {
        return round((float) ($item['price'] ?? 0), 2);
    }

    /**
     * @return array{created: int, skipped: int, updated: int, imaged: int, errors: int, samples: list<array<string, mixed>>, error_reasons: list<string>}
     */
    public function sync(bool $dryRun = false, ?string $path = null, ?string $sku = null): array
    {
        $previousSocial = SocialPostingService::$suppress;
        SocialPostingService::$suppress = true;

        try {
            return $this->runSync($dryRun, $path, $sku);
        } finally {
            SocialPostingService::$suppress = $previousSocial;
        }
    }

    /**
     * @return array{created: int, skipped: int, updated: int, imaged: int, errors: int, samples: list<array<string, mixed>>, error_reasons: list<string>}
     */
    protected function runSync(bool $dryRun = false, ?string $path = null, ?string $sku = null): array
    {
        $this->categories->ensureCanonicalTree();
        $this->ensureBrands($dryRun);
        $this->publishListingImages();

        $created = 0;
        $skipped = 0;
        $updated = 0;
        $imaged = 0;
        $errors = 0;
        $samples = [];
        $errorReasons = [];

        foreach ($this->items($path) as $item) {
            if ($sku !== null && $sku !== '' && strcasecmp((string) $item['sku'], $sku) !== 0) {
                continue;
            }

            try {
                $existing = $this->findExisting($item);

                if ($existing) {
                    $refreshImage = $this->shouldRefreshImages($existing, $item);
                    if ($refreshImage && ! $dryRun) {
                        if ($this->attachListingImages($existing, $item, replace: true)) {
                            $imaged++;
                        }
                    } elseif ($refreshImage && $dryRun) {
                        $imaged++;
                    }

                    $updatePrice = abs((float) $existing->price - $this->retailPrice($item)) >= 0.01;
                    $updateCopy = $this->shouldRefreshCopy($existing, $item);

                    if ($updatePrice || $updateCopy || $refreshImage) {
                        if (! $dryRun && ($updatePrice || $updateCopy)) {
                            $this->applyListingContent($existing, $item, $updatePrice);
                        }
                        if ($updatePrice || $updateCopy) {
                            $updated++;
                        }
                        if (count($samples) < 25) {
                            $reasons = [];
                            if ($updatePrice) {
                                $reasons[] = 'Was R'.number_format((float) $existing->price, 2).' → R'.number_format($this->retailPrice($item), 2);
                            }
                            if ($updateCopy) {
                                $reasons[] = 'Listing copy refreshed';
                            }
                            if ($refreshImage) {
                                $reasons[] = 'Photos attached';
                            }
                            $samples[] = [
                                'action' => $dryRun ? 'would_update' : 'updated',
                                'sku' => $item['sku'],
                                'name' => $item['name'],
                                'price' => $this->retailPrice($item),
                                'reason' => implode('; ', $reasons),
                            ];
                        }

                        continue;
                    }

                    $skipped++;
                    if (count($samples) < 25) {
                        $samples[] = [
                            'action' => 'skipped',
                            'sku' => $item['sku'],
                            'name' => $item['name'],
                            'reason' => 'Already on store',
                        ];
                    }

                    continue;
                }

                if ($dryRun) {
                    $created++;
                    if (count($samples) < 25) {
                        $samples[] = [
                            'action' => 'would_create',
                            'sku' => $item['sku'],
                            'name' => $item['name'],
                            'price' => $this->retailPrice($item),
                        ];
                    }

                    continue;
                }

                $product = $this->createProduct($item);
                try {
                    if ($this->attachListingImages($product, $item)) {
                        $imaged++;
                    }
                } catch (\Throwable $imageError) {
                    Log::warning('High-margin catalog photo failed', [
                        'sku' => $item['sku'] ?? null,
                        'message' => $imageError->getMessage(),
                    ]);
                }
                $created++;
                if (count($samples) < 25) {
                    $samples[] = [
                        'action' => 'created',
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                    ];
                }
            } catch (\Throwable $e) {
                $errors++;
                $reason = $this->shortError($e);
                $errorReasons[$reason] = ($errorReasons[$reason] ?? 0) + 1;
                Log::warning('High-margin catalog item failed', [
                    'sku' => $item['sku'] ?? null,
                    'message' => $e->getMessage(),
                ]);
                if (count(array_filter($samples, fn ($sample) => ($sample['action'] ?? '') === 'error')) < 15) {
                    $samples[] = [
                        'action' => 'error',
                        'sku' => $item['sku'] ?? null,
                        'name' => $item['name'] ?? null,
                        'reason' => $reason,
                    ];
                }
            }
        }

        if (! $dryRun && ($created > 0 || $imaged > 0 || $updated > 0)) {
            $this->deduper->clearCache();
            Cache::forget('home.product_rows_v1');
            Cache::forget('home.product_rows_v2');
            Cache::forget('home.brands');
            Cache::forget('home.brands_v3');
            Cache::forget('feeds.google-merchant.xml');
            Cache::forget('sitemap.xml');
            Cache::forget('sitemap.main.v4');
            Cache::forget('sitemap.images.v3');
        }

        $error_reasons = [];
        foreach ($errorReasons as $reason => $count) {
            $error_reasons[] = ((int) $count) > 1 ? $reason.' ('.$count.' times)' : $reason;
        }

        return compact('created', 'skipped', 'updated', 'imaged', 'errors', 'samples', 'error_reasons');
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function findExisting(array $item): ?Product
    {
        $sku = trim((string) ($item['sku'] ?? ''));
        if ($sku === '') {
            return null;
        }

        return Product::withTrashed()->where('sku', $sku)->first();
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function createProduct(array $item): Product
    {
        return Product::withoutEvents(fn () => $this->createProductRecord($item));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function createProductRecord(array $item): Product
    {
        $retail = $this->retailPrice($item);
        $category = $this->categories->resolveCategoryForFilter((string) $item['category_path']);
        $availability = (string) ($item['availability'] ?? 'available_on_order');
        $payload = [
            'category_id' => $category?->id,
            'sku' => (string) $item['sku'],
            'model_number' => (string) ($item['mpn'] ?? $item['sku']),
            'name' => (string) $item['name'],
            'slug' => $this->uniqueSlug($item),
            'short_description' => $this->copy->shortDescription($item),
            'description' => $this->copy->descriptionHtml($item),
            'price' => $retail,
            'sale_price' => null,
            'cost_price' => $this->impliedCostPrice($item),
            'stock_quantity' => 0,
            'manage_stock' => false,
            'in_stock' => true,
            'brand' => (string) ($item['brand'] ?? 'Urban Focus'),
            'google_product_category' => $this->copy->googleProductCategory($item),
            'warranty_months' => $this->copy->warrantyMonths($item),
            'delivery_days' => (int) (config("specialist.availability.{$availability}.days") ?: 21),
            'specifications' => $this->copy->specifications($item),
            'meta_title' => $this->copy->metaTitle($item),
            'meta_description' => $this->copy->metaDescription($item),
            'meta_keywords' => $this->copy->metaKeywords($item),
            'is_featured' => (bool) ($item['featured'] ?? false),
            'is_deal' => false,
            'is_active' => true,
        ];

        try {
            return Product::create($payload);
        } catch (\Throwable $e) {
            if (! $this->isLostConnection($e)) {
                throw $e;
            }

            DB::reconnect();

            return Product::create($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function attachListingImages(Product $product, array $item, bool $replace = false): bool
    {
        $files = $this->listingImageFiles($item);
        if ($files === []) {
            return false;
        }

        if ($product->images()->exists() && ! $replace) {
            return false;
        }

        if ($replace) {
            $this->clearListingImages($product);
        }

        $alts = array_values(array_filter(array_map('strval', $item['image_alts'] ?? [])));
        $attached = 0;

        foreach ($files as $index => $source) {
            $contents = (string) file_get_contents($source);
            $path = $this->images->storeProductImageFromBinary($contents, (int) $product->id, pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
            if (! $path) {
                continue;
            }

            $alt = trim((string) ($alts[$index] ?? ''));
            if ($alt === '') {
                $alt = $product->imageAlt();
            }

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'alt_text' => $alt,
                'sort_order' => $index,
                'is_primary' => $index === 0,
            ]);
            $attached++;
        }

        if ($attached === 0) {
            return false;
        }

        $this->rememberListingPhoto($product, $this->listingPhotoToken($files));

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function shouldRefreshImages(Product $product, array $item): bool
    {
        $files = $this->listingImageFiles($item);
        if ($files === []) {
            return false;
        }

        if (! $product->images()->exists()) {
            return true;
        }

        $specs = is_array($product->specifications) ? $product->specifications : [];
        $current = trim((string) ($specs[self::LISTING_PHOTO_SPEC_KEY] ?? ''));

        return $current !== $this->listingPhotoToken($files);
    }

    /**
     * @param  list<string>  $files
     */
    protected function listingPhotoToken(array $files): string
    {
        $parts = [];
        foreach ($files as $path) {
            $size = is_file($path) ? (int) filesize($path) : 0;
            $parts[] = basename($path).':'.$size;
        }

        return implode('|', $parts);
    }

    protected function rememberListingPhoto(Product $product, string $token): void
    {
        $specs = is_array($product->specifications) ? $product->specifications : [];
        $specs[self::LISTING_PHOTO_SPEC_KEY] = $token;

        Product::withoutEvents(fn () => $product->update(['specifications' => $specs]));
        $product->setAttribute('specifications', $specs);
    }

    protected function clearListingImages(Product $product): void
    {
        foreach ($product->images()->get() as $image) {
            $this->images->delete((string) $image->path);
            $image->delete();
        }
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    public function listingImageFiles(array $item): array
    {
        $files = [];
        foreach ($this->imageDirectories($item) as $directory) {
            $found = [];
            foreach (['jpg', 'jpeg', 'png', 'webp', 'JPG', 'JPEG', 'PNG', 'WEBP'] as $ext) {
                $found = array_merge($found, glob(rtrim($directory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'*.'.$ext) ?: []);
            }
            $found = array_values(array_unique($found));
            natsort($found);
            foreach ($found as $path) {
                if (is_file($path) && is_readable($path) && filesize($path) > 512) {
                    $files[] = $path;
                }
            }
            if ($files !== []) {
                break;
            }
        }

        return array_values(array_unique($files));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function imageDirectories(array $item): array
    {
        $sku = (string) ($item['sku'] ?? '');
        $dirs = [];
        if (! empty($item['image_dir']) && is_string($item['image_dir'])) {
            $dirs[] = $item['image_dir'];
        }
        if ($sku !== '') {
            $dirs[] = base_path('public/images/high-margin/'.$sku);
            $dirs[] = public_path('images/high-margin/'.$sku);
        }

        return array_values(array_unique(array_filter($dirs, fn ($dir) => is_dir($dir))));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function impliedCostPrice(array $item): float
    {
        $retail = $this->retailPrice($item);
        if ($retail <= 0) {
            return 0.0;
        }

        $markup = $this->pricing->markupPercentFor($retail, null, [
            'name' => (string) ($item['name'] ?? ''),
            'brand' => (string) ($item['brand'] ?? ''),
            'category_path' => (string) ($item['category_path'] ?? ''),
        ]);
        $fee = (float) config('pricing.payment_fee_percent', 0);
        $divisor = (1 + ($markup / 100)) * (1 + ($fee / 100));

        return $divisor > 0 ? round($retail / $divisor, 2) : $retail;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function shouldRefreshCopy(Product $product, array $item): bool
    {
        return trim((string) $product->description) !== trim($this->copy->descriptionHtml($item))
            || trim((string) $product->short_description) !== trim($this->copy->shortDescription($item))
            || trim((string) $product->meta_description) !== trim($this->copy->metaDescription($item))
            || abs((float) $product->price - $this->retailPrice($item)) >= 0.01;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function applyListingContent(Product $product, array $item, bool $updatePrice = true): void
    {
        $availability = (string) ($item['availability'] ?? 'available_on_order');
        $specs = $this->copy->specifications($item);
        $existingSpecs = is_array($product->specifications) ? $product->specifications : [];
        if (! empty($existingSpecs[self::LISTING_PHOTO_SPEC_KEY])) {
            $specs[self::LISTING_PHOTO_SPEC_KEY] = $existingSpecs[self::LISTING_PHOTO_SPEC_KEY];
        }

        $payload = [
            'name' => (string) $item['name'],
            'short_description' => $this->copy->shortDescription($item),
            'description' => $this->copy->descriptionHtml($item),
            'meta_title' => $this->copy->metaTitle($item),
            'meta_description' => $this->copy->metaDescription($item),
            'meta_keywords' => $this->copy->metaKeywords($item),
            'specifications' => $specs,
            'warranty_months' => $this->copy->warrantyMonths($item),
            'google_product_category' => $this->copy->googleProductCategory($item),
            'delivery_days' => (int) (config("specialist.availability.{$availability}.days") ?: 21),
            'model_number' => (string) ($item['mpn'] ?? $item['sku']),
            'brand' => (string) ($item['brand'] ?? 'Urban Focus'),
            'is_active' => true,
        ];

        $category = $this->categories->resolveCategoryForFilter((string) $item['category_path']);
        if ($category) {
            $payload['category_id'] = $category->id;
        }

        if ($updatePrice) {
            $payload['price'] = $this->retailPrice($item);
            $payload['cost_price'] = $this->impliedCostPrice($item);
        }

        Product::withoutEvents(fn () => $product->update($payload));
    }

    protected function ensureBrands(bool $dryRun): void
    {
        if ($dryRun) {
            return;
        }

        foreach (config('specialist.brands', []) as $brand) {
            Brand::query()->updateOrCreate(
                ['slug' => Str::slug((string) $brand['name'])],
                [
                    'name' => $brand['name'],
                    'website' => $brand['website'] ?? null,
                    'is_active' => true,
                ]
            );
        }
    }

    protected function publishListingImages(): void
    {
        $source = base_path('public/images/high-margin');
        $target = public_path('images/high-margin');

        if (! is_dir($source) || realpath($source) === realpath($target)) {
            return;
        }

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $relative = substr($file->getPathname(), strlen($source) + 1);
            $dest = $target.DIRECTORY_SEPARATOR.$relative;
            $directory = dirname($dest);
            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                continue;
            }

            if (! is_file($dest) || filemtime($file->getPathname()) > filemtime($dest)) {
                @copy($file->getPathname(), $dest);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function uniqueSlug(array $item): string
    {
        $base = $this->copy->preferredSlug($item) ?: 'product';
        $sku = (string) ($item['sku'] ?? '');
        $candidate = $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $skuSlug = Str::slug($sku);
            $candidate = Str::limit($base.($skuSlug !== '' ? '-'.$skuSlug : '').($suffix > 2 ? '-'.$suffix : ''), 255, '');
            $suffix++;
            if ($suffix > 50) {
                $candidate = Str::limit($base.'-'.substr(sha1($sku.$suffix), 0, 8), 255, '');
                break;
            }
        }

        return $candidate;
    }

    protected function shortError(\Throwable $e): string
    {
        $message = trim(preg_replace('/\s+/', ' ', $e->getMessage()) ?? $e->getMessage());
        $message = preg_replace('/\(Connection: [^)]+\)\s*/', '', $message) ?? $message;

        return Str::limit($message, 220, '');
    }

    protected function isLostConnection(\Throwable $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'has gone away')
            || str_contains($message, 'Lost connection')
            || str_contains($message, '2006');
    }
}
