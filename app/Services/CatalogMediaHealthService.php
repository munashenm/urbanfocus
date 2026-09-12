<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogMediaHealthService
{
    public const AUDIT_JSON = 'catalog-media-audit.json';

    public const FULL_AUDIT_CSV = 'catalogue-media-audit.csv';

    public const MISSING_IMAGES_CSV = 'missing-product-images.csv';

    public const DATA_PROBLEMS_CSV = 'catalogue-data-problems.csv';

    public const MANUFACTURER_CSV = 'manufacturer-image-candidates.csv';

    /** @var array<string, string> */
    protected array $remoteCache = [];

    /** @var array<string, array<string, mixed>> */
    protected array $specialistBySku = [];

    /** @var array<string, array<string, mixed>> */
    protected array $targetRangeBySku = [];

    /** @var array<string, list<array{id:int,brand:string,path:string}>> */
    protected array $validLocalBySkuBrand = [];

    protected bool $checkRemote = false;

    public function __construct(
        protected ImageService $images,
        protected SpecialistCatalogService $specialist,
        protected TargetRangeCatalogService $targetRange,
    ) {}

    /**
     * Cheap SQL counts for the admin dashboard. Does not HTTP-check images.
     *
     * @return array<string, mixed>
     */
    public function liveSummary(): array
    {
        $active = Product::query()->where('is_active', true);

        $missingImage = (clone $active)->whereDoesntHave('images', function ($q) {
            $q->whereNotNull('path')->where('path', '!=', '');
            foreach (['%placeholder%', '%coming-soon%', '%no-image%', '%noimage%'] as $like) {
                $q->where('path', 'not like', $like);
            }
        })->count();

        $duplicateSkuGroups = Product::query()
            ->where('is_active', true)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->selectRaw('LOWER(TRIM(sku)) as sku_key, COUNT(*) as cnt')
            ->groupByRaw('LOWER(TRIM(sku))')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $last = $this->lastAuditCounts();

        return [
            'total_products' => Product::query()->count(),
            'total_active' => (clone $active)->count(),
            'missing_images' => $missingImage,
            'broken_images' => $last['broken_images'] ?? null,
            'placeholder_images' => $last['placeholder_images'] ?? null,
            'valid_images' => $last['valid_images'] ?? null,
            'missing_descriptions' => (clone $active)->whereRaw(
                "LENGTH(TRIM(COALESCE(short_description, ''))) + LENGTH(TRIM(COALESCE(description, ''))) < 10"
            )->count(),
            'missing_skus' => (clone $active)->where(function ($q) {
                $q->whereNull('sku')->orWhere('sku', '');
            })->count(),
            'missing_prices' => (clone $active)->whereRaw('COALESCE(sale_price, price) <= 0')->count(),
            'missing_brands' => (clone $active)->where(function ($q) {
                $q->whereNull('brand')->orWhere('brand', '');
            })->count(),
            'missing_titles' => (clone $active)->where(function ($q) {
                $q->whereNull('name')->orWhere('name', '');
            })->count(),
            'duplicate_sku_groups' => $duplicateSkuGroups->count(),
            'duplicate_sku_rows' => (int) $duplicateSkuGroups->sum('cnt'),
            'audited_at' => $last['audited_at'] ?? null,
            'recovered_images' => $last['recovered_images'] ?? null,
            'still_requiring_manual_images' => $last['still_requiring_manual_images'] ?? null,
            'reports_ready' => $this->reportPath(self::MISSING_IMAGES_CSV) !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function lastAuditCounts(): array
    {
        $path = storage_path('app/'.self::AUDIT_JSON);
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        return is_array($decoded['counts'] ?? null) ? $decoded['counts'] : [];
    }

    /**
     * @return list<int>
     */
    public function lastIssueProductIds(string $issue): array
    {
        $path = storage_path('app/'.self::AUDIT_JSON);
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        $ids = $decoded['issue_ids'][$issue] ?? [];

        return is_array($ids) ? array_values(array_map('intval', $ids)) : [];
    }

    public function reportAbsolutePath(string $filename): ?string
    {
        $allowed = [
            self::FULL_AUDIT_CSV,
            self::MISSING_IMAGES_CSV,
            self::DATA_PROBLEMS_CSV,
            self::MANUFACTURER_CSV,
        ];
        if (! in_array($filename, $allowed, true)) {
            return null;
        }

        return $this->reportPath($filename);
    }

    /**
     * @return array<string, mixed>
     */
    public function run(bool $checkRemote = false, bool $recover = false): array
    {
        $this->checkRemote = $checkRemote;
        $this->remoteCache = [];
        $this->loadCatalogIndexes();

        $recovered = [];
        if ($recover) {
            $recovered = $this->recoverHighConfidenceImages();
        }

        $rows = [];
        $pathUsage = [];
        $issueIds = [
            'missing_image' => [],
            'broken_image' => [],
            'placeholder_image' => [],
            'missing_title' => [],
            'missing_sku' => [],
            'missing_brand' => [],
            'missing_price' => [],
            'missing_description' => [],
            'duplicate_sku' => [],
            'broken_url' => [],
            'low_resolution' => [],
            'generic_duplicate' => [],
            'invalid_external' => [],
        ];

        Product::query()
            ->with(['category', 'images'])
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById((int) config('catalog_media.chunk_size', 100), function (Collection $chunk) use (&$rows, &$pathUsage) {
                foreach ($chunk as $product) {
                    $row = $this->inspectProduct($product);
                    $rows[] = $row;
                    $key = $row['image_key'];
                    if ($key !== '') {
                        $pathUsage[$key]['count'] = ($pathUsage[$key]['count'] ?? 0) + 1;
                        $pathUsage[$key]['brands'][$row['brand_key']] = true;
                        $pathUsage[$key]['ids'][] = $product->id;
                    }
                }
            });

        $genericIds = $this->genericDuplicateIds($pathUsage);
        foreach ($rows as &$row) {
            if (in_array((int) $row['id'], $genericIds, true) && $row['image_status'] === 'valid') {
                $row['image_status'] = 'generic_duplicate';
                $row['problems'][] = 'duplicate generic image used across unrelated products';
            }
            $row['problem_detected'] = implode('; ', $row['problems']);
        }
        unset($row);

        $duplicateSkuIds = $this->duplicateSkuProductIds();
        foreach ($rows as &$row) {
            if (in_array((int) $row['id'], $duplicateSkuIds, true)) {
                $row['problems'][] = 'duplicate SKU';
                $row['problem_detected'] = implode('; ', $row['problems']);
            }
        }
        unset($row);

        foreach ($rows as $row) {
            foreach ($this->rowIssueKeys($row) as $issue) {
                $issueIds[$issue][] = (int) $row['id'];
            }
        }

        $counts = $this->summarise($rows, $recovered, $duplicateSkuIds);
        $huawei = $this->investigateSku((string) config('catalog_media.huawei_sku', '02356UUM'));

        $report = [
            'audited_at' => now()->toIso8601String(),
            'check_remote' => $checkRemote,
            'recover' => $recover,
            'counts' => $counts,
            'huawei_02356uum' => $huawei,
            'recovered' => $recovered,
            'issue_ids' => $issueIds,
        ];

        $this->writeOutputs($report, $rows);

        return $report;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function investigateSku(string $sku): array
    {
        $needle = strtoupper(preg_replace('/\s+/', '', $sku) ?? '');
        if ($needle === '') {
            return [];
        }

        $matches = Product::withTrashed()
            ->with(['category', 'images'])
            ->where(function ($q) use ($sku, $needle) {
                $q->whereRaw("UPPER(REPLACE(TRIM(sku), ' ', '')) = ?", [$needle])
                    ->orWhere('sku', 'like', '%'.$sku.'%')
                    ->orWhere('model_number', 'like', '%'.$sku.'%')
                    ->orWhere('name', 'like', '%'.$sku.'%')
                    ->orWhere('slug', 'like', '%'.Str::slug($sku).'%');
            })
            ->orderBy('id')
            ->get();

        return $matches->map(function (Product $product) {
            $problems = [];
            $name = trim((string) $product->name);
            $meta = trim((string) $product->meta_title);
            if ($name === '') {
                $problems[] = 'missing title';
            }
            if ($meta !== '' && $name !== '' && ! str_contains(mb_strtolower($meta), mb_strtolower($name))) {
                $problems[] = 'meta title does not contain product name';
            }
            if ($product->trashed()) {
                $problems[] = 'archived';
            }
            if (! $product->is_active) {
                $problems[] = 'inactive';
            }
            if (trim((string) $product->sku) === '') {
                $problems[] = 'missing SKU';
            }

            $image = $this->inspectImage($product);

            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'brand' => $product->brand,
                'slug' => $product->slug,
                'meta_title' => $product->meta_title,
                'seo_title' => $product->seoTitle(),
                'is_active' => $product->is_active,
                'trashed' => $product->trashed(),
                'product_url' => $this->productUrl($product),
                'image_status' => $image['status'],
                'image_url' => $image['url'],
                'title_consistent' => $problems === [] || ! in_array('meta title does not contain product name', $problems, true),
                'problems' => $problems,
            ];
        })->all();
    }

    /**
     * @return list<array{id:int,sku:?string,source:string}>
     */
    protected function recoverHighConfidenceImages(): array
    {
        $this->indexValidLocalImages();
        $recovered = [];

        Product::query()
            ->with('images')
            ->where('is_active', true)
            ->orderBy('id')
            ->chunkById((int) config('catalog_media.chunk_size', 100), function (Collection $chunk) use (&$recovered) {
                foreach ($chunk as $product) {
                    $image = $this->inspectImage($product);
                    if (in_array($image['status'], ['valid', 'low_resolution', 'generic_duplicate'], true)) {
                        continue;
                    }

                    $source = $this->recoverProductImage($product);
                    if ($source === null) {
                        continue;
                    }

                    $product->unsetRelation('images');
                    $product->load('images');
                    $recovered[] = [
                        'id' => $product->id,
                        'sku' => $product->sku,
                        'source' => $source,
                    ];
                }
            });

        return $recovered;
    }

    protected function recoverProductImage(Product $product): ?string
    {
        if ($this->attachOrphanLocalFiles($product)) {
            return 'local_product_folder';
        }

        $sku = trim((string) $product->sku);
        if ($sku === '') {
            return null;
        }

        $skuKey = $this->skuKey($sku);

        $specialistItem = $this->specialistBySku[$skuKey] ?? null;
        if (is_array($specialistItem) && $this->brandMatches($product, $specialistItem['brand'] ?? null)) {
            if ($this->specialist->attachListingImage($product, $specialistItem, replace: $product->images()->exists())) {
                $this->refreshImageAlt($product);

                return 'specialist_catalog_sku';
            }
        }

        $targetItem = $this->targetRangeBySku[$skuKey] ?? null;
        if (is_array($targetItem) && $this->brandMatches($product, $targetItem['brand'] ?? null)) {
            if ($product->images()->exists()) {
                foreach ($product->images as $existing) {
                    $this->images->delete((string) $existing->path);
                    $existing->delete();
                }
                $product->unsetRelation('images');
            }
            if ($this->targetRange->attachListingImage($product, $targetItem)) {
                $this->refreshImageAlt($product);

                return 'target_range_catalog_sku';
            }
        }

        $skuFile = $this->localSkuImageFile($sku);
        if ($skuFile !== null && $this->attachLocalFile($product, $skuFile, replace: true)) {
            return 'local_sku_filename';
        }

        $brandKey = $this->brandKey((string) $product->brand);
        if ($skuKey !== '' && $brandKey !== '') {
            $siblings = $this->validLocalBySkuBrand[$skuKey.'|'.$brandKey] ?? [];
            foreach ($siblings as $sibling) {
                if ((int) $sibling['id'] === (int) $product->id) {
                    continue;
                }
                $absolute = $this->absoluteLocalPath($sibling['path']);
                if ($absolute && $this->attachLocalFile($product, $absolute, replace: true)) {
                    return 'sibling_sku_brand_image';
                }
            }
        }

        return null;
    }

    protected function attachOrphanLocalFiles(Product $product): bool
    {
        if ($product->images()->exists()) {
            return false;
        }

        $directory = 'products/'.$product->id;
        $files = Storage::disk('public')->files($directory);
        foreach ($files as $file) {
            $absolute = $this->absoluteLocalPath($file);
            if ($absolute === null) {
                continue;
            }
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                continue;
            }
            ProductImage::create([
                'product_id' => $product->id,
                'path' => $file,
                'alt_text' => $product->imageAlt(),
                'sort_order' => 0,
                'is_primary' => true,
            ]);

            return true;
        }

        $publicDir = public_path('storage/'.$directory);
        if (is_dir($publicDir)) {
            $pattern = defined('GLOB_BRACE')
                ? $publicDir.'/*.{jpg,jpeg,png,gif,webp}'
                : $publicDir.'/*';
            $flags = defined('GLOB_BRACE') ? GLOB_BRACE : 0;
            foreach (glob($pattern, $flags) ?: [] as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (! in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                    continue;
                }
                $relative = $directory.'/'.basename($file);
                ProductImage::create([
                    'product_id' => $product->id,
                    'path' => $relative,
                    'alt_text' => $product->imageAlt(),
                    'sort_order' => 0,
                    'is_primary' => true,
                ]);

                return true;
            }
        }

        return false;
    }

    protected function attachLocalFile(Product $product, string $absolutePath, bool $replace = false): bool
    {
        if (! is_readable($absolutePath)) {
            return false;
        }

        if ($replace) {
            foreach ($product->images()->get() as $existing) {
                if ((string) $existing->path !== $absolutePath) {
                    $this->images->delete((string) $existing->path);
                }
                $existing->delete();
            }
        } elseif ($product->images()->exists()) {
            return false;
        }

        $contents = (string) file_get_contents($absolutePath);
        $path = $this->images->storeProductImageFromBinary(
            $contents,
            (int) $product->id,
            pathinfo($absolutePath, PATHINFO_EXTENSION) ?: 'jpg'
        );
        if (! $path) {
            return false;
        }

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'alt_text' => $product->imageAlt(),
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        return true;
    }

    protected function refreshImageAlt(Product $product): void
    {
        $alt = $product->fresh()?->imageAlt() ?? $product->imageAlt();
        $product->images()->update(['alt_text' => $alt]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function inspectProduct(Product $product): array
    {
        $problems = [];
        $image = $this->inspectImage($product);

        if (trim((string) $product->name) === '') {
            $problems[] = 'missing title';
        }
        if (trim((string) $product->sku) === '') {
            $problems[] = 'missing SKU';
        }
        if (trim((string) $product->brand) === '') {
            $problems[] = 'missing brand';
        }
        if ((float) $product->effective_price <= 0) {
            $problems[] = 'missing price';
        }
        if (strlen($product->googleFeedDescription()) < 10) {
            $problems[] = 'missing description';
        }
        if (trim((string) $product->slug) === '') {
            $problems[] = 'broken product URL';
        }

        if ($image['status'] === 'missing') {
            $problems[] = 'no image';
        } elseif ($image['status'] === 'empty_url') {
            $problems[] = 'null/empty image URL';
        } elseif ($image['status'] === 'broken') {
            $problems[] = $image['detail'] ?: 'broken image URL';
        } elseif ($image['status'] === 'placeholder') {
            $problems[] = 'placeholder/default image';
        } elseif ($image['status'] === 'invalid_external') {
            $problems[] = 'invalid external image';
        } elseif ($image['status'] === 'low_resolution') {
            $problems[] = 'extremely low-resolution image';
        } elseif ($image['status'] === 'remote_unchecked') {
            $problems[] = 'remote image not HTTP-checked';
        }

        $url = $this->productUrl($product);
        if ($url === '') {
            $problems[] = 'broken product URL';
        }

        return [
            'id' => $product->id,
            'sku' => $product->sku,
            'name' => $product->name,
            'brand' => $product->brand,
            'brand_key' => $this->brandKey((string) $product->brand),
            'category' => $product->category?->fullPathLabel() ?: $product->category?->name,
            'price' => $product->effective_price,
            'current_image_url' => $image['url'],
            'image_status' => $image['status'],
            'image_key' => $image['key'],
            'product_url' => $url,
            'problems' => array_values(array_unique($problems)),
            'problem_detected' => '',
        ];
    }

    /**
     * @return array{status:string,url:?string,key:string,detail:?string}
     */
    protected function inspectImage(Product $product): array
    {
        $image = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
        if (! $image) {
            return ['status' => 'missing', 'url' => null, 'key' => '', 'detail' => null];
        }

        $path = trim((string) $image->path);
        if ($path === '') {
            return ['status' => 'empty_url', 'url' => null, 'key' => '', 'detail' => null];
        }

        if (is_catalog_placeholder_path($path)) {
            return [
                'status' => 'placeholder',
                'url' => storage_public_url($path),
                'key' => $this->imageKey($path),
                'detail' => null,
            ];
        }

        if ($this->isInvalidExternal($path)) {
            return [
                'status' => 'invalid_external',
                'url' => $path,
                'key' => $this->imageKey($path),
                'detail' => 'invalid external image URL',
            ];
        }

        $localPath = $this->localStoragePath($path);
        if ($localPath !== null) {
            $absolute = $this->absoluteLocalPath($localPath);
            if ($absolute === null) {
                return [
                    'status' => 'broken',
                    'url' => storage_public_url($localPath),
                    'key' => $this->imageKey($localPath),
                    'detail' => 'image file missing on disk',
                ];
            }

            $resolution = $this->resolutionStatus($absolute);
            if ($resolution === 'low_resolution') {
                return [
                    'status' => 'low_resolution',
                    'url' => storage_public_url($localPath),
                    'key' => $this->imageKey($localPath),
                    'detail' => 'shortest side below '.config('catalog_media.min_image_side', 300).'px',
                ];
            }

            return [
                'status' => 'valid',
                'url' => storage_public_url($localPath),
                'key' => $this->imageKey($localPath),
                'detail' => null,
            ];
        }

        $remote = $this->remoteStatus($path);
        if ($remote === 'remote_unchecked') {
            return ['status' => 'remote_unchecked', 'url' => $path, 'key' => $this->imageKey($path), 'detail' => null];
        }
        if ($remote !== 'ok') {
            return [
                'status' => 'broken',
                'url' => $path,
                'key' => $this->imageKey($path),
                'detail' => $remote,
            ];
        }

        return ['status' => 'valid', 'url' => $path, 'key' => $this->imageKey($path), 'detail' => null];
    }

    protected function isInvalidExternal(string $path): bool
    {
        if (! str_starts_with($path, 'http://') && ! str_starts_with($path, 'https://')) {
            return false;
        }

        $parts = parse_url($path);
        if (! is_array($parts) || empty($parts['host']) || empty($parts['scheme'])) {
            return true;
        }

        $scheme = strtolower((string) $parts['scheme']);

        return ! in_array($scheme, ['http', 'https'], true);
    }

    protected function localStoragePath(string $path): ?string
    {
        $normalized = str_replace('\\', '/', trim($path));
        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://')) {
            $appHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
            $urlHost = strtolower((string) (parse_url($normalized, PHP_URL_HOST) ?? ''));
            $urlPath = (string) (parse_url($normalized, PHP_URL_PATH) ?? '');
            if ($appHost !== '' && $urlHost === $appHost && str_starts_with($urlPath, '/storage/')) {
                return ltrim(substr($urlPath, strlen('/storage/')), '/');
            }

            return null;
        }

        return ltrim($normalized, '/');
    }

    protected function absoluteLocalPath(string $path): ?string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');
        $candidates = [
            Storage::disk('public')->path($path),
            public_path('storage/'.$path),
            public_path($path),
            base_path('public/'.$path),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate) && is_readable($candidate) && filesize($candidate) > 32) {
                return $candidate;
            }
        }

        return null;
    }

    protected function resolutionStatus(string $absolute): string
    {
        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        if ($ext === 'svg') {
            return 'valid';
        }

        $info = @getimagesize($absolute);
        if (! is_array($info) || empty($info[0]) || empty($info[1])) {
            return 'valid';
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $minSide = (int) config('catalog_media.min_image_side', 300);
        $minPixels = (int) config('catalog_media.min_image_pixels', 40000);

        if (min($width, $height) < $minSide || ($width * $height) < $minPixels) {
            return 'low_resolution';
        }

        return 'valid';
    }

    protected function remoteStatus(string $url): string
    {
        if (isset($this->remoteCache[$url])) {
            return $this->remoteCache[$url];
        }

        if (! $this->checkRemote) {
            return $this->remoteCache[$url] = 'remote_unchecked';
        }

        $code = $this->httpStatus($url, 'HEAD');
        if (in_array($code, [405, 403, 0], true)) {
            $code = $this->httpStatus($url, 'GET');
        }

        $result = match (true) {
            $code >= 200 && $code < 400 => 'ok',
            $code === 404 => 'image returning 404',
            $code === 403 => 'image returning 403',
            default => 'broken image URL (HTTP '.$code.')',
        };

        return $this->remoteCache[$url] = $result;
    }

    protected function httpStatus(string $url, string $method): int
    {
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'timeout' => (int) config('catalog_media.remote_timeout', 8),
                'ignore_errors' => true,
                'follow_location' => 1,
                'user_agent' => 'UrbanFocus-CatalogAudit/1.0',
                'header' => $method === 'GET' ? "Range: bytes=0-1023\r\n" : '',
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $headers = @get_headers($url, true, $context);
        if (! is_array($headers) || ! isset($headers[0]) || ! is_string($headers[0])) {
            return 0;
        }

        if (preg_match('/\s(\d{3})\s/', $headers[0], $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    /**
     * @param  array<string, array{count:int,brands:array<string,bool>,ids:list<int>}>  $pathUsage
     * @return list<int>
     */
    protected function genericDuplicateIds(array $pathUsage): array
    {
        $minProducts = (int) config('catalog_media.generic_duplicate_min_products', 8);
        $minBrands = (int) config('catalog_media.generic_duplicate_min_brands', 2);
        $ids = [];

        foreach ($pathUsage as $meta) {
            $brandCount = count(array_filter(array_keys($meta['brands']), fn ($brand) => $brand !== ''));
            if (($meta['count'] ?? 0) >= $minProducts && $brandCount >= $minBrands) {
                foreach ($meta['ids'] as $id) {
                    $ids[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<int>
     */
    protected function duplicateSkuProductIds(): array
    {
        $keys = Product::query()
            ->where('is_active', true)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->selectRaw('LOWER(TRIM(sku)) as sku_key')
            ->groupByRaw('LOWER(TRIM(sku))')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sku_key');

        if ($keys->isEmpty()) {
            return [];
        }

        return Product::query()
            ->where('is_active', true)
            ->where(function ($q) use ($keys) {
                foreach ($keys as $key) {
                    $q->orWhereRaw('LOWER(TRIM(sku)) = ?', [$key]);
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected function rowIssueKeys(array $row): array
    {
        $keys = [];
        $status = (string) $row['image_status'];
        if (in_array($status, ['missing', 'empty_url'], true)) {
            $keys[] = 'missing_image';
        }
        if ($status === 'broken') {
            $keys[] = 'broken_image';
        }
        if ($status === 'placeholder') {
            $keys[] = 'placeholder_image';
        }
        if ($status === 'low_resolution') {
            $keys[] = 'low_resolution';
        }
        if ($status === 'generic_duplicate') {
            $keys[] = 'generic_duplicate';
        }
        if ($status === 'invalid_external') {
            $keys[] = 'invalid_external';
        }

        $problems = $row['problems'] ?? [];
        foreach ($problems as $problem) {
            $map = [
                'missing title' => 'missing_title',
                'missing SKU' => 'missing_sku',
                'missing brand' => 'missing_brand',
                'missing price' => 'missing_price',
                'missing description' => 'missing_description',
                'duplicate SKU' => 'duplicate_sku',
                'broken product URL' => 'broken_url',
            ];
            if (isset($map[$problem])) {
                $keys[] = $map[$problem];
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array<string, mixed>>  $recovered
     * @param  list<int>  $duplicateSkuIds
     * @return array<string, mixed>
     */
    protected function summarise(array $rows, array $recovered, array $duplicateSkuIds): array
    {
        $statusCounts = [
            'valid' => 0,
            'missing' => 0,
            'empty_url' => 0,
            'broken' => 0,
            'placeholder' => 0,
            'invalid_external' => 0,
            'low_resolution' => 0,
            'generic_duplicate' => 0,
            'remote_unchecked' => 0,
        ];
        $missingTitles = 0;
        $missingSkus = 0;
        $missingPrices = 0;
        $missingBrands = 0;
        $missingDescriptions = 0;
        $brokenUrls = 0;
        $manual = 0;

        foreach ($rows as $row) {
            $status = (string) $row['image_status'];
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
            $problems = $row['problems'] ?? [];
            if (in_array('missing title', $problems, true)) {
                $missingTitles++;
            }
            if (in_array('missing SKU', $problems, true)) {
                $missingSkus++;
            }
            if (in_array('missing price', $problems, true)) {
                $missingPrices++;
            }
            if (in_array('missing brand', $problems, true)) {
                $missingBrands++;
            }
            if (in_array('missing description', $problems, true)) {
                $missingDescriptions++;
            }
            if (in_array('broken product URL', $problems, true)) {
                $brokenUrls++;
            }
            if (in_array($status, ['missing', 'empty_url', 'broken', 'placeholder', 'invalid_external'], true)) {
                $manual++;
            }
        }

        return [
            'audited_at' => now()->toIso8601String(),
            'total_scanned' => count($rows),
            'valid_images' => $statusCounts['valid'] + $statusCounts['remote_unchecked'],
            'valid_images_confirmed' => $statusCounts['valid'],
            'remote_unchecked' => $statusCounts['remote_unchecked'],
            'missing_images' => $statusCounts['missing'] + $statusCounts['empty_url'],
            'broken_images' => $statusCounts['broken'],
            'placeholder_images' => $statusCounts['placeholder'],
            'invalid_external_images' => $statusCounts['invalid_external'],
            'low_resolution_images' => $statusCounts['low_resolution'],
            'generic_duplicate_images' => $statusCounts['generic_duplicate'],
            'recovered_images' => count($recovered),
            'still_requiring_manual_images' => $manual,
            'missing_titles' => $missingTitles,
            'missing_skus' => $missingSkus,
            'missing_prices' => $missingPrices,
            'missing_brands' => $missingBrands,
            'missing_descriptions' => $missingDescriptions,
            'broken_product_urls' => $brokenUrls,
            'duplicate_sku_rows' => count($duplicateSkuIds),
            'duplicate_skus' => count(array_unique($duplicateSkuIds)) > 0
                ? (int) Product::query()
                    ->whereIn('id', $duplicateSkuIds)
                    ->selectRaw('COUNT(DISTINCT LOWER(TRIM(sku))) as groups')
                    ->value('groups')
                : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     * @param  list<array<string, mixed>>  $rows
     */
    protected function writeOutputs(array $report, array $rows): void
    {
        $dir = storage_path('app/'.config('catalog_media.reports_dir', 'reports'));
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        file_put_contents(
            storage_path('app/'.self::AUDIT_JSON),
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->writeCsv($dir.'/'.self::FULL_AUDIT_CSV, [
            'SKU', 'Product name', 'Brand', 'Category', 'Price', 'Current image URL', 'Image status', 'Product URL', 'Problem detected',
        ], array_map(fn (array $row) => [
            $row['sku'],
            $row['name'],
            $row['brand'],
            $row['category'],
            $row['price'],
            $row['current_image_url'],
            $row['image_status'],
            $row['product_url'],
            $row['problem_detected'],
        ], $rows));

        $missing = array_values(array_filter($rows, fn (array $row) => in_array($row['image_status'], ['missing', 'empty_url', 'broken', 'placeholder', 'invalid_external'], true)));
        $this->writeCsv($dir.'/'.self::MISSING_IMAGES_CSV, [
            'SKU', 'Brand', 'Product Name', 'Product URL', 'Category',
        ], array_map(fn (array $row) => [
            $row['sku'],
            $row['brand'],
            $row['name'],
            $row['product_url'],
            $row['category'],
        ], $missing));

        $dataProblems = array_values(array_filter($rows, function (array $row) {
            foreach ($row['problems'] as $problem) {
                if (! str_contains($problem, 'image') && $problem !== 'remote image not HTTP-checked') {
                    return true;
                }
            }

            return false;
        }));
        $this->writeCsv($dir.'/'.self::DATA_PROBLEMS_CSV, [
            'SKU', 'Product name', 'Brand', 'Category', 'Price', 'Product URL', 'Problem detected',
        ], array_map(fn (array $row) => [
            $row['sku'],
            $row['name'],
            $row['brand'],
            $row['category'],
            $row['price'],
            $row['product_url'],
            $row['problem_detected'],
        ], $dataProblems));

        $manufacturers = array_map('mb_strtolower', config('catalog_media.manufacturer_brands', []));
        $candidates = array_values(array_filter($missing, function (array $row) use ($manufacturers) {
            $brand = mb_strtolower(trim((string) $row['brand']));
            if ($brand === '' || trim((string) $row['sku']) === '') {
                return false;
            }
            foreach ($manufacturers as $name) {
                if ($name !== '' && (str_contains($brand, $name) || str_contains($name, $brand))) {
                    return true;
                }
            }

            return false;
        }));
        $this->writeCsv($dir.'/'.self::MANUFACTURER_CSV, [
            'SKU', 'Brand', 'Product Name', 'Category', 'Product URL', 'Image status', 'Match rule',
        ], array_map(fn (array $row) => [
            $row['sku'],
            $row['brand'],
            $row['name'],
            $row['category'],
            $row['product_url'],
            $row['image_status'],
            'Exact brand + SKU/MPN against the manufacturer official catalogue. Do not scrape or hotlink.',
        ], $candidates));

        $publicReports = base_path('reports');
        if (! is_dir($publicReports)) {
            @mkdir($publicReports, 0755, true);
        }
        foreach ([self::MISSING_IMAGES_CSV, self::DATA_PROBLEMS_CSV, self::MANUFACTURER_CSV, self::FULL_AUDIT_CSV] as $file) {
            $from = $dir.'/'.$file;
            if (is_file($from)) {
                @copy($from, $publicReports.'/'.$file);
            }
        }
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<mixed>>  $rows
     */
    protected function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');
        if ($handle === false) {
            return;
        }
        fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            fputcsv($handle, array_map(fn ($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value), $row));
        }
        fclose($handle);
    }

    protected function loadCatalogIndexes(): void
    {
        $this->specialistBySku = [];
        $this->targetRangeBySku = [];

        try {
            foreach ($this->specialist->items() as $item) {
                $key = $this->skuKey((string) ($item['sku'] ?? ''));
                if ($key !== '') {
                    $this->specialistBySku[$key] = $item;
                }
            }
        } catch (\Throwable) {
            // Catalog file may be absent in tests.
        }

        try {
            foreach ($this->targetRange->items() as $item) {
                $key = $this->skuKey((string) ($item['sku'] ?? ''));
                if ($key !== '') {
                    $this->targetRangeBySku[$key] = $item;
                }
            }
        } catch (\Throwable) {
            // Catalog file may be absent in tests.
        }
    }

    protected function indexValidLocalImages(): void
    {
        $this->validLocalBySkuBrand = [];

        Product::query()
            ->with('images')
            ->where('is_active', true)
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->orderBy('id')
            ->chunkById(200, function (Collection $chunk) {
                foreach ($chunk as $product) {
                    $image = $this->inspectImage($product);
                    if ($image['status'] !== 'valid' || ! $image['url']) {
                        continue;
                    }
                    $imageRow = $product->images->firstWhere('is_primary', true) ?? $product->images->first();
                    $local = $this->localStoragePath((string) ($imageRow?->path ?? ''));
                    if ($local === null || $this->absoluteLocalPath($local) === null) {
                        continue;
                    }
                    $key = $this->skuKey((string) $product->sku).'|'.$this->brandKey((string) $product->brand);
                    $this->validLocalBySkuBrand[$key][] = [
                        'id' => (int) $product->id,
                        'brand' => (string) $product->brand,
                        'path' => $local,
                    ];
                }
            });
    }

    protected function localSkuImageFile(string $sku): ?string
    {
        $stems = array_filter([
            Str::slug($sku),
            strtoupper($sku),
            $sku,
        ]);

        foreach ($stems as $stem) {
            foreach (['jpg', 'jpeg', 'png', 'webp'] as $ext) {
                foreach ([
                    public_path('images/specialist/products/'.$stem.'.'.$ext),
                    base_path('public/images/specialist/products/'.$stem.'.'.$ext),
                    public_path('images/target-range/'.$stem.'.'.$ext),
                ] as $path) {
                    if (is_file($path) && filesize($path) > 512) {
                        return $path;
                    }
                }
            }
        }

        $bySku = config('specialist.photos.by_sku', []);
        $url = $bySku[strtoupper($sku)] ?? $bySku[$sku] ?? null;
        if (is_string($url) && str_starts_with($url, '/') && is_file(public_path(ltrim($url, '/')))) {
            return public_path(ltrim($url, '/'));
        }

        return null;
    }

    protected function brandMatches(Product $product, mixed $catalogBrand): bool
    {
        $productBrand = $this->brandKey((string) $product->brand);
        $catalogKey = $this->brandKey((string) $catalogBrand);
        if ($productBrand === '' || $catalogKey === '') {
            return $catalogKey === '' ? $productBrand === '' : false;
        }

        return $productBrand === $catalogKey
            || str_contains($productBrand, $catalogKey)
            || str_contains($catalogKey, $productBrand);
    }

    protected function skuKey(string $sku): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($sku)) ?? '');
    }

    protected function brandKey(string $brand): string
    {
        return mb_strtolower(trim($brand));
    }

    protected function imageKey(string $path): string
    {
        $path = strtolower(str_replace('\\', '/', trim($path)));
        $path = preg_replace('/[?#].*$/', '', $path) ?? $path;

        return $path;
    }

    protected function productUrl(Product $product): string
    {
        if (trim((string) $product->slug) === '') {
            return '';
        }

        try {
            return route('products.show', $product);
        } catch (\Throwable) {
            return '';
        }
    }

    protected function reportPath(string $filename): ?string
    {
        $path = storage_path('app/'.config('catalog_media.reports_dir', 'reports').'/'.$filename);

        return is_file($path) ? $path : null;
    }
}
