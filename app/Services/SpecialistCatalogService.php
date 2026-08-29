<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Social\SocialPostingService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpecialistCatalogService
{
    public const CATALOG_RANGE_SPEC_KEY = 'Urban Focus range';

    public const CATALOG_RANGE_SPEC_VALUE = 'Specialist technology';

    public const LISTING_PHOTO_SPEC_KEY = 'Listing photo';

    /** @var array<string, string> */
    protected array $downloadedPhotoUrls = [];

    /** @var Collection<int, object>|null */
    protected ?Collection $index = null;

    public function __construct(
        protected CategoryMapperService $categories,
        protected ProductPricingService $pricing,
        protected CatalogDeduper $deduper,
        protected ImageService $images,
        protected SpecialistListingCopy $copy,
    ) {}

    public function catalogPath(): string
    {
        return (string) config('catalog.specialist_path', database_path('data/specialist-products.php'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(?string $path = null): array
    {
        $path ??= $this->catalogPath();

        if (! is_readable($path)) {
            throw new \RuntimeException('Specialist catalog file is not readable: '.$path);
        }

        $decoded = str_ends_with(strtolower($path), '.php')
            ? require $path
            : json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Specialist catalog file is not valid: '.$path);
        }

        return array_values(array_filter($decoded, fn ($item) => is_array($item) && ! empty($item['sku']) && ! empty($item['name'])));
    }

    /**
     * @return array{created: int, skipped: int, updated: int, imaged: int, errors: int, samples: list<array<string, mixed>>}
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
        $this->refreshIndex();
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
                    $exactSku = $this->isExactCatalogSku($existing, $item);
                    $missingImage = ! $existing->images()->exists();
                    $refreshImage = $exactSku && $this->shouldRefreshImage($existing, $item);

                    if ($missingImage || $refreshImage) {
                        if (! $dryRun && $this->attachListingImage($existing, $item, replace: $refreshImage)) {
                            $imaged++;
                        } elseif ($dryRun) {
                            $imaged++;
                        }
                    }

                    $updatePrice = $exactSku && $this->shouldUpdatePrice($existing, $item);
                    $updateCopy = $exactSku && $this->shouldRefreshCopy($existing, $item);

                    if ($updatePrice || $updateCopy) {
                        $newPrice = $this->retailStreetPrice($item);
                        $reasons = [];
                        if ($updatePrice) {
                            $reasons[] = 'Was R'.number_format((float) $existing->price, 0).' → R'.number_format($newPrice, 0);
                        }
                        if ($updateCopy) {
                            $reasons[] = 'SEO listing refreshed';
                        }
                        if (! $dryRun) {
                            $this->applyListingContent($existing, $item, $updatePrice);
                        }
                        $updated++;
                        if (count($samples) < 25) {
                            $samples[] = [
                                'action' => $dryRun ? 'would_update' : 'updated',
                                'sku' => $item['sku'],
                                'name' => $item['name'],
                                'price' => $newPrice,
                                'reason' => implode('; ', $reasons),
                            ];
                        }

                        continue;
                    }

                    if ($missingImage || $refreshImage) {
                        if (count($samples) < 25) {
                            $samples[] = [
                                'action' => $dryRun ? 'would_refresh_photo' : 'photo_refreshed',
                                'sku' => $item['sku'],
                                'name' => $item['name'],
                                'reason' => 'Manufacturer product photo attached',
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
                            'price' => $this->retailStreetPrice($item),
                        ];
                    }

                    continue;
                }

                $product = $this->createProduct($item);
                try {
                    if ($this->attachListingImage($product, $item)) {
                        $imaged++;
                    }
                } catch (\Throwable $imageError) {
                    Log::warning('Specialist catalog photo failed', [
                        'sku' => $item['sku'] ?? null,
                        'message' => $imageError->getMessage(),
                    ]);
                }
                $this->rememberCreated($product);
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
                Log::warning('Specialist catalog item failed', [
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
        foreach ($this->productIndex() as $row) {
            if ($this->skuMatches($row, $item)) {
                return $this->hydrate($row);
            }
        }

        foreach ($this->productIndex() as $row) {
            if ($this->nameExcluded($row->name ?? '', $item)) {
                continue;
            }

            if ($this->nameMatches($row->name ?? '', $item)) {
                return $this->hydrate($row);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function retailStreetPrice(array $item): float
    {
        $street = (float) ($item['street_price'] ?? 0);
        if ($street <= 0) {
            return 0.0;
        }

        return $this->roundRetail($street * (1 + ($this->topupPercent() / 100)));
    }

    public function topupPercent(): float
    {
        return max(0, (float) config('pricing.specialist_topup_percent', config('pricing.target_range_topup_percent', 15)));
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
        $retail = $this->retailStreetPrice($item);
        $category = $this->categories->resolveCategoryForFilter((string) $item['category_path']);
        $availability = (string) ($item['availability'] ?? 'eu_stock');
        $payload = [
            'category_id' => $category?->id,
            'sku' => (string) $item['sku'],
            'model_number' => (string) ($item['mpn'] ?? $item['sku']),
            'name' => (string) $item['name'],
            'slug' => $this->uniqueSlug((string) $item['name'], (string) $item['sku']),
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
            'delivery_days' => (int) (config("specialist.availability.{$availability}.days") ?: 10),
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
    public function attachListingImage(Product $product, array $item, bool $replace = false): bool
    {
        if ($product->images()->exists() && ! $replace) {
            return false;
        }

        $source = $this->listingImagePath($item);
        if ($source === null || ! is_readable($source)) {
            return false;
        }

        if ($replace) {
            $this->clearListingImages($product);
        }

        $contents = (string) file_get_contents($source);
        $path = $this->images->storeProductImageFromBinary($contents, (int) $product->id, pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
        if (! $path) {
            return false;
        }

        $alt = Str::limit(trim($product->brand.' '.$product->name.' product photo for sale in South Africa'), 255, '');

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'alt_text' => $alt,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $this->rememberListingPhoto($product, $this->listingPhotoToken($source));

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function shouldRefreshImage(Product $product, array $item): bool
    {
        if (! $this->isCatalogOwnedProduct($product, $item)) {
            return false;
        }

        $source = $this->listingImagePath($item);
        if ($source === null) {
            return false;
        }

        $specs = is_array($product->specifications) ? $product->specifications : [];
        $current = trim((string) ($specs[self::LISTING_PHOTO_SPEC_KEY] ?? ''));

        return $current !== $this->listingPhotoToken($source);
    }

    protected function listingPhotoToken(string $path): string
    {
        $size = is_file($path) ? (int) filesize($path) : 0;

        return basename($path).':'.$size;
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
     */
    protected function listingImagePath(array $item): ?string
    {
        $this->ensureCatalogPhoto($item);

        foreach ($this->listingImageCandidates($item) as $file) {
            foreach ([
                base_path('public/images/specialist/'.$file),
                public_path('images/specialist/'.$file),
            ] as $path) {
                if (is_file($path) && is_readable($path) && filesize($path) > 512) {
                    return $path;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return list<string>
     */
    protected function listingImageCandidates(array $item): array
    {
        $sku = Str::slug((string) ($item['sku'] ?? ''));
        $key = Str::slug((string) ($item['image_key'] ?? $this->copy->family($item) ?: 'solution'));
        $candidates = [];

        foreach (array_filter([$sku, $key !== '' ? $key : null]) as $stem) {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                $candidates[] = 'products/'.$stem.'.'.$ext;
            }
        }

        $candidates[] = ($key !== '' ? $key : 'solution').'.jpg';

        return array_values(array_unique($candidates));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function listingImageFile(array $item): string
    {
        return $this->listingImageCandidates($item)[0] ?? 'solution.jpg';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function listingPhotoUrl(array $item): ?string
    {
        $sku = strtoupper((string) ($item['sku'] ?? ''));
        $key = (string) ($item['image_key'] ?? '');
        $bySku = config('specialist.photos.by_sku', []);
        $byKey = config('specialist.photos.by_image_key', []);

        if ($sku !== '' && is_string($bySku[$sku] ?? null) && $bySku[$sku] !== '') {
            return $bySku[$sku];
        }

        if ($key !== '' && is_string($byKey[$key] ?? null) && $byKey[$key] !== '') {
            return $byKey[$key];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function ensureCatalogPhoto(array $item): void
    {
        $url = $this->listingPhotoUrl($item);
        if ($url === null) {
            return;
        }

        $sku = Str::slug((string) ($item['sku'] ?? ''));
        $key = Str::slug((string) ($item['image_key'] ?? ''));
        $bySku = config('specialist.photos.by_sku', []);
        $skuKey = strtoupper((string) ($item['sku'] ?? ''));

        if ($sku !== '' && isset($bySku[$skuKey])) {
            $this->downloadCatalogPhoto($url, 'products/'.$sku);
        } elseif ($key !== '') {
            $this->downloadCatalogPhoto($url, 'products/'.$key);
        }
    }

    protected function downloadCatalogPhoto(string $url, string $stem): ?string
    {
        $existing = $this->existingPhotoForStem($stem);
        if ($existing !== null) {
            $this->downloadedPhotoUrls[$url] = $existing;

            return $existing;
        }

        if (isset($this->downloadedPhotoUrls[$url]) && is_file($this->downloadedPhotoUrls[$url])) {
            $source = $this->downloadedPhotoUrls[$url];
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
            $copied = $this->writeCatalogPhoto($stem, $extension, (string) file_get_contents($source));

            return $copied ?? $source;
        }

        if (app()->environment('testing')) {
            return null;
        }

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 18,
                    'follow_location' => 1,
                    'header' => "Accept: image/jpeg,image/png,image/webp,*/*\r\n",
                    'user_agent' => 'Mozilla/5.0 (compatible; UrbanFocusCatalog/1.0; +https://www.urbanfocus.co.za)',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);

            $contents = @file_get_contents($url, false, $context);
            if ($contents === false || $contents === '' || ! $this->looksLikeImage($contents)) {
                return null;
            }

            $extension = $this->extensionFromBinary($contents, $url);
            $path = $this->writeCatalogPhoto($stem, $extension, $contents);
            if ($path !== null) {
                $this->downloadedPhotoUrls[$url] = $path;
            }

            return $path;
        } catch (\Throwable $e) {
            Log::warning('Specialist catalog photo download failed', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function existingPhotoForStem(string $stem): ?string
    {
        foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
            foreach ([
                base_path('public/images/specialist/'.$stem.'.'.$ext),
                public_path('images/specialist/'.$stem.'.'.$ext),
            ] as $path) {
                if (is_file($path) && filesize($path) > 512) {
                    return $path;
                }
            }
        }

        return null;
    }

    protected function writeCatalogPhoto(string $stem, string $extension, string $contents): ?string
    {
        $relative = ltrim($stem, '/').'.'.$extension;
        $destinations = [
            base_path('public/images/specialist/'.$relative),
            public_path('images/specialist/'.$relative),
        ];

        $written = null;
        foreach (array_unique($destinations) as $path) {
            $directory = dirname($path);
            if (! is_dir($directory) && ! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
                continue;
            }

            if (@file_put_contents($path, $contents) === false) {
                continue;
            }

            $written = $path;
        }

        return $written;
    }

    protected function looksLikeImage(string $contents): bool
    {
        if (function_exists('getimagesizefromstring') && @getimagesizefromstring($contents) !== false) {
            return true;
        }

        return str_starts_with($contents, "\xFF\xD8\xFF")
            || str_starts_with($contents, "\x89PNG")
            || str_starts_with($contents, 'GIF')
            || str_starts_with($contents, 'RIFF');
    }

    protected function extensionFromBinary(string $contents, string $url): string
    {
        if (str_starts_with($contents, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($contents, 'RIFF')) {
            return 'webp';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) ? $extension : 'jpg';
    }

    protected function publishListingImages(): void
    {
        $this->prefetchCatalogPhotos();

        $source = base_path('public/images/specialist');
        $target = public_path('images/specialist');

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

    protected function prefetchCatalogPhotos(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $bySku = config('specialist.photos.by_sku', []);
        $byKey = config('specialist.photos.by_image_key', []);

        foreach ($bySku as $sku => $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }
            $this->downloadCatalogPhoto($url, 'products/'.Str::slug((string) $sku));
        }

        foreach ($byKey as $key => $url) {
            if (! is_string($url) || $url === '') {
                continue;
            }
            $this->downloadCatalogPhoto($url, 'products/'.Str::slug((string) $key));
        }
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

    /**
     * @param  array<string, mixed>  $item
     */
    public function impliedCostPrice(array $item): float
    {
        $retail = $this->retailStreetPrice($item);
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
    public function shouldUpdatePrice(Product $product, array $item): bool
    {
        if (! $this->isCatalogOwnedProduct($product, $item)) {
            return false;
        }

        return abs((float) $product->price - $this->retailStreetPrice($item)) >= 0.01;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isExactCatalogSku(Product $product, array $item): bool
    {
        $catalogSku = $this->normalizeCode((string) ($item['sku'] ?? ''));

        return $catalogSku !== '' && $this->normalizeCode((string) $product->sku) === $catalogSku;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function isCatalogOwnedProduct(Product $product, array $item): bool
    {
        if (! $this->isExactCatalogSku($product, $item)) {
            return false;
        }

        $specs = is_array($product->specifications) ? $product->specifications : [];

        return ($specs[self::CATALOG_RANGE_SPEC_KEY] ?? '') === self::CATALOG_RANGE_SPEC_VALUE;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function shouldRefreshCopy(Product $product, array $item): bool
    {
        return trim((string) $product->description) !== trim($this->copy->descriptionHtml($item))
            || trim((string) $product->short_description) !== trim($this->copy->shortDescription($item))
            || trim((string) $product->meta_description) !== trim($this->copy->metaDescription($item));
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function applyListingContent(Product $product, array $item, bool $updatePrice = true): void
    {
        $availability = (string) ($item['availability'] ?? 'eu_stock');
        $specs = $this->copy->specifications($item);
        $existingSpecs = is_array($product->specifications) ? $product->specifications : [];
        if (! empty($existingSpecs[self::LISTING_PHOTO_SPEC_KEY])) {
            $specs[self::LISTING_PHOTO_SPEC_KEY] = $existingSpecs[self::LISTING_PHOTO_SPEC_KEY];
        }

        $payload = [
            'short_description' => $this->copy->shortDescription($item),
            'description' => $this->copy->descriptionHtml($item),
            'meta_title' => $this->copy->metaTitle($item),
            'meta_description' => $this->copy->metaDescription($item),
            'meta_keywords' => $this->copy->metaKeywords($item),
            'specifications' => $specs,
            'warranty_months' => $this->copy->warrantyMonths($item),
            'google_product_category' => $this->copy->googleProductCategory($item),
            'delivery_days' => (int) (config("specialist.availability.{$availability}.days") ?: 10),
            'model_number' => (string) ($item['mpn'] ?? $item['sku']),
        ];

        if ($updatePrice) {
            $payload['price'] = $this->retailStreetPrice($item);
            $payload['cost_price'] = $this->impliedCostPrice($item);
        }

        Product::withoutEvents(fn () => $product->update($payload));
    }

    protected function roundRetail(float $price): float
    {
        if ($price <= 0) {
            return 0.0;
        }

        $roundTo = max(1, (int) config('pricing.round_to', 50));
        $mode = config('pricing.round_mode', 'up');
        $rounded = $mode === 'nearest'
            ? (int) (round($price / $roundTo) * $roundTo)
            : (int) (ceil($price / $roundTo) * $roundTo);

        return (float) max($rounded, $roundTo);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function skuMatches(object $row, array $item): bool
    {
        $needles = array_values(array_filter(array_map(
            fn ($sku) => $this->normalizeCode((string) $sku),
            $item['match_skus'] ?? [$item['sku'] ?? '']
        )));

        if ($needles === []) {
            return false;
        }

        foreach ([$row->sku ?? '', $row->model_number ?? ''] as $value) {
            $normalized = $this->normalizeCode((string) $value);
            if ($normalized !== '' && in_array($normalized, $needles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function nameMatches(string $name, array $item): bool
    {
        foreach ($item['match_terms'] ?? [] as $term) {
            if ($this->containsPhrase($name, (string) $term)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function nameExcluded(string $name, array $item): bool
    {
        foreach ($item['exclude_name_terms'] ?? [] as $term) {
            if ($this->containsPhrase($name, (string) $term)) {
                return true;
            }
        }

        return false;
    }

    protected function containsPhrase(string $haystack, string $phrase): bool
    {
        $words = $this->words($phrase);
        if ($words === []) {
            return false;
        }

        return str_contains(' '.$this->wordString($haystack).' ', ' '.implode(' ', $words).' ');
    }

    public function normalizeCode(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $value));
    }

    /** @return list<string> */
    protected function words(string $value): array
    {
        $parts = preg_split('/[^a-z0-9+]+/i', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);

        return $parts ?: [];
    }

    protected function wordString(string $value): string
    {
        return implode(' ', $this->words($value));
    }

    protected function uniqueSlug(string $name, string $sku): string
    {
        $base = Str::slug($name) ?: 'product';
        $skuSlug = Str::slug($this->normalizeCode($sku));
        $candidate = $skuSlug !== '' ? Str::limit($base.'-'.$skuSlug, 255, '') : $base;
        $suffix = 2;

        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $candidate = Str::limit($base.($skuSlug !== '' ? '-'.$skuSlug : '').'-'.$suffix, 255, '');
            $suffix++;
            if ($suffix > 50) {
                $candidate = Str::limit($base.'-'.$suffix.'-'.substr(sha1($sku.$suffix), 0, 8), 255, '');
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

    /** @return Collection<int, object> */
    protected function productIndex(): Collection
    {
        return $this->index ??= Product::withTrashed()->get(['id', 'sku', 'model_number', 'name', 'brand', 'slug']);
    }

    protected function refreshIndex(): void
    {
        $this->index = null;
    }

    protected function rememberCreated(Product $product): void
    {
        $this->index = $this->productIndex()->push($product);
    }

    protected function hydrate(object $row): Product
    {
        return Product::withTrashed()->findOrFail((int) ($row->id ?? 0));
    }
}
