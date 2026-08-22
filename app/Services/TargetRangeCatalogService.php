<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TargetRangeCatalogService
{
    public const CATALOG_RANGE_SPEC_KEY = 'Urban Focus range';

    public const CATALOG_RANGE_SPEC_VALUE = 'Target catalogue';

    /** @var Collection<int, object>|null */
    protected ?Collection $index = null;

    public function __construct(
        protected CategoryMapperService $categories,
        protected ProductPricingService $pricing,
        protected CatalogDeduper $deduper,
        protected ImageService $images,
    ) {}

    public function catalogPath(): string
    {
        return (string) config('catalog.target_range_path', database_path('data/target-range-products.json'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(?string $path = null): array
    {
        $path ??= $this->catalogPath();

        if (! is_readable($path)) {
            throw new \RuntimeException('Target-range catalog file is not readable: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new \RuntimeException('Target-range catalog file is not valid JSON: '.$path);
        }

        return array_values(array_filter($decoded, fn ($item) => is_array($item) && ! empty($item['sku']) && ! empty($item['name'])));
    }

    /**
     * @return array{created: int, skipped: int, updated: int, imaged: int, errors: int, samples: list<array<string, mixed>>}
     */
    public function sync(bool $dryRun = false, ?string $path = null, ?string $sku = null): array
    {
        $this->refreshIndex();
        $this->categories->ensureCanonicalTree();
        $this->publishListingImages();

        $created = 0;
        $skipped = 0;
        $updated = 0;
        $imaged = 0;
        $errors = 0;
        $samples = [];

        foreach ($this->items($path) as $item) {
            if ($sku !== null && $sku !== '' && strcasecmp((string) $item['sku'], $sku) !== 0) {
                continue;
            }

            try {
                $existing = $this->findExisting($item);

                if ($existing) {
                    if (! $existing->images()->exists()) {
                        if (! $dryRun && $this->attachListingImage($existing, $item)) {
                            $imaged++;
                        } elseif ($dryRun) {
                            $imaged++;
                        }
                    }

                    if ($this->shouldUpdatePrice($existing, $item)) {
                        $newPrice = $this->retailStreetPrice($item);
                        if (! $dryRun) {
                            $this->applyRetailPrice($existing, $item);
                        }
                        $updated++;
                        if (count($samples) < 25) {
                            $samples[] = [
                                'action' => $dryRun ? 'would_update' : 'updated',
                                'list_id' => $item['list_id'] ?? null,
                                'sku' => $item['sku'],
                                'name' => $item['name'],
                                'price' => $newPrice,
                                'reason' => 'Was R'.number_format((float) $existing->price, 0).' → R'.number_format($newPrice, 0).' ('.$this->topupPercent().'% catalogue top-up)',
                            ];
                        }

                        continue;
                    }

                    $skipped++;
                    if (count($samples) < 25) {
                        $samples[] = [
                            'action' => 'skipped',
                            'list_id' => $item['list_id'] ?? null,
                            'sku' => $item['sku'],
                            'name' => $item['name'],
                            'reason' => 'Already on store: '.trim(($existing->sku ?: 'no-sku').' '.$existing->name),
                        ];
                    }

                    continue;
                }

                if ($dryRun) {
                    $created++;
                    if (count($samples) < 25) {
                        $samples[] = [
                            'action' => 'would_create',
                            'list_id' => $item['list_id'] ?? null,
                            'sku' => $item['sku'],
                            'name' => $item['name'],
                            'price' => $this->retailStreetPrice($item),
                        ];
                    }

                    continue;
                }

                $product = $this->createProduct($item);
                if ($this->attachListingImage($product, $item)) {
                    $imaged++;
                }
                $this->rememberCreated($product);
                $created++;

                if (count($samples) < 25) {
                    $samples[] = [
                        'action' => 'created',
                        'list_id' => $item['list_id'] ?? null,
                        'sku' => $product->sku,
                        'name' => $product->name,
                        'price' => (float) $product->price,
                    ];
                }
            } catch (\Throwable $e) {
                $errors++;
                if (count($samples) < 25) {
                    $samples[] = [
                        'action' => 'error',
                        'list_id' => $item['list_id'] ?? null,
                        'sku' => $item['sku'] ?? null,
                        'name' => $item['name'] ?? null,
                        'reason' => $e->getMessage(),
                    ];
                }
            }
        }

        if (! $dryRun && ($created > 0 || $imaged > 0 || $updated > 0)) {
            $this->deduper->clearCache();
            Cache::forget('home.product_rows_v1');
        }

        return compact('created', 'skipped', 'updated', 'imaged', 'errors', 'samples');
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
     * Street research price plus the target-range top-up, rounded to the store step.
     *
     * @param  array<string, mixed>  $item
     */
    public function retailStreetPrice(array $item): float
    {
        $street = (float) ($item['street_price'] ?? 0);
        if ($street <= 0) {
            return 0.0;
        }

        $topup = $this->topupPercent();
        $withTopup = $street * (1 + ($topup / 100));

        return $this->roundRetail($withTopup);
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

        $context = [
            'name' => (string) ($item['name'] ?? ''),
            'brand' => (string) ($item['brand'] ?? ''),
            'category_path' => (string) ($item['category_path'] ?? ''),
        ];

        $markup = $this->pricing->markupPercentFor($retail, null, $context);
        $fee = (float) config('pricing.payment_fee_percent', 0);
        $divisor = (1 + ($markup / 100)) * (1 + ($fee / 100));

        return $divisor > 0 ? round($retail / $divisor, 2) : $retail;
    }

    /**
     * Reprice a listing we previously created from this catalogue. Store imports
     * matched only by name (or a different SKU) are left unchanged.
     *
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
    protected function isCatalogOwnedProduct(Product $product, array $item): bool
    {
        $catalogSku = $this->normalizeCode((string) ($item['sku'] ?? ''));
        if ($catalogSku === '' || $this->normalizeCode((string) $product->sku) !== $catalogSku) {
            return false;
        }

        $specs = is_array($product->specifications) ? $product->specifications : [];
        if (($specs[self::CATALOG_RANGE_SPEC_KEY] ?? '') === self::CATALOG_RANGE_SPEC_VALUE) {
            return true;
        }

        $current = (float) $product->price;
        $street = (float) ($item['street_price'] ?? 0);
        $retail = $this->retailStreetPrice($item);

        return abs($current - $street) < 0.51 || abs($current - $retail) < 0.51;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function applyRetailPrice(Product $product, array $item): void
    {
        $specs = is_array($product->specifications) ? $product->specifications : [];
        $specs[self::CATALOG_RANGE_SPEC_KEY] = self::CATALOG_RANGE_SPEC_VALUE;

        $product->update([
            'price' => $this->retailStreetPrice($item),
            'cost_price' => $this->impliedCostPrice($item),
            'specifications' => $specs,
        ]);
    }

    public function topupPercent(): float
    {
        return max(0, (float) config('pricing.target_range_topup_percent', 10));
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
    protected function createProduct(array $item): Product
    {
        $retail = $this->retailStreetPrice($item);
        $category = $this->categories->resolveCategoryForFilter((string) $item['category_path']);
        $short = trim((string) ($item['short_description'] ?? ''));
        $angle = trim((string) ($item['sales_angle'] ?? ''));

        return Product::create([
            'category_id' => $category?->id,
            'sku' => (string) $item['sku'],
            'model_number' => (string) $item['sku'],
            'name' => (string) $item['name'],
            'slug' => $this->uniqueSlug((string) $item['name'], (string) $item['sku']),
            'short_description' => $short,
            'description' => $this->description($item),
            'price' => $retail,
            'sale_price' => null,
            'cost_price' => $this->impliedCostPrice($item),
            'stock_quantity' => 0,
            'manage_stock' => false,
            'in_stock' => true,
            'brand' => (string) ($item['brand'] ?? ''),
            'warranty_months' => $this->warrantyMonths((string) ($item['category_path'] ?? ''), (string) $item['name']),
            'delivery_days' => $this->deliveryDays((string) ($item['category_path'] ?? '')),
            'specifications' => array_filter([
                'Model' => (string) $item['sku'],
                'Brand' => (string) ($item['brand'] ?? ''),
                'Sales focus' => $angle,
                self::CATALOG_RANGE_SPEC_KEY => self::CATALOG_RANGE_SPEC_VALUE,
                'Availability' => 'Available to order — typically 5–10 working days',
            ]),
            'meta_title' => Str::limit((string) $item['name'].' | Urban Focus', 70, ''),
            'meta_description' => Str::limit($short !== '' ? $short : (string) $item['name'], 160, ''),
            'is_featured' => (bool) ($item['featured'] ?? false),
            'is_deal' => false,
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    public function attachListingImage(Product $product, array $item): bool
    {
        if ($product->images()->exists()) {
            return false;
        }

        $source = $this->listingImagePath($item);
        if ($source === null || ! is_readable($source)) {
            return false;
        }

        $contents = (string) file_get_contents($source);
        $path = $this->images->storeProductImageFromBinary($contents, (int) $product->id, pathinfo($source, PATHINFO_EXTENSION) ?: 'jpg');
        if (! $path) {
            return false;
        }

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'alt_text' => $product->name,
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        return true;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function listingImagePath(array $item): ?string
    {
        $file = $this->listingImageFile($item);

        foreach ([
            base_path('public/images/target-range/'.$file),
            public_path('images/target-range/'.$file),
        ] as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }

    protected function publishListingImages(): void
    {
        $source = base_path('public/images/target-range');
        $target = public_path('images/target-range');

        if (! is_dir($source) || realpath($source) === realpath($target)) {
            return;
        }

        if (! is_dir($target) && ! @mkdir($target, 0755, true) && ! is_dir($target)) {
            return;
        }

        foreach (glob($source.'/tr-*.jpg') ?: [] as $file) {
            $dest = $target.'/'.basename($file);
            if (! is_file($dest) || filemtime($file) > filemtime($dest)) {
                @copy($file, $dest);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function listingImageFile(array $item): string
    {
        $name = mb_strtolower(trim(($item['name'] ?? '').' '.($item['brand'] ?? '').' '.($item['category_path'] ?? '').' '.($item['sku'] ?? '')));

        if (str_contains($name, 'macbook')) {
            return 'tr-macbook.jpg';
        }
        if (str_contains($name, 'toughbook 40') || str_contains($name, 'getac s510') || str_contains($name, 'rugged laptop')) {
            return 'tr-rugged-laptop.jpg';
        }
        if (str_contains($name, 'tablet') || str_contains($name, 'toughbook g2') || str_contains($name, 'getac f110') || str_contains($name, 'zebra') || str_contains($name, 'oukitel') || str_contains($name, 'ulefone') || str_contains($name, 'tab active')) {
            return 'tr-rugged-tablet.jpg';
        }
        if (str_contains($name, 'zbook') || str_contains($name, 'pro max 16') || str_contains($name, 'thinkpad p16') || str_contains($name, 'proart') || str_contains($name, 'workstation')) {
            return 'tr-workstation.jpg';
        }
        if (str_contains($name, 'x1 carbon') || str_contains($name, 'omnibook') || str_contains($name, 'premium') || str_contains($name, 'flip')) {
            return 'tr-laptop-executive.jpg';
        }
        if (preg_match('/\b16\b|16-inch|g1i 16|pro 16|l16/', $name)) {
            return 'tr-laptop-16.jpg';
        }
        if (str_contains($name, 'laptop') || str_contains($name, 'thinkpad') || str_contains($name, 'elitebook') || str_contains($name, 'computing-office/laptops')) {
            return 'tr-laptop-14.jpg';
        }
        if (str_contains($name, 'meetingboard')) {
            return 'tr-meetingboard.jpg';
        }
        if (str_contains($name, 'rally') || str_contains($name, 'tap ip')) {
            return 'tr-rally-bar.jpg';
        }
        if (str_contains($name, 'jetson') || str_contains($name, 'minisforum') || str_contains($name, 'orin')) {
            return 'tr-edge-ai.jpg';
        }
        if (str_contains($name, 'solar')) {
            return 'tr-solar-camera.jpg';
        }
        if (str_contains($name, 'ptz') || str_contains($name, 'q6135')) {
            return 'tr-cctv-ptz.jpg';
        }
        if (str_contains($name, 'nvr')) {
            return 'tr-nvr.jpg';
        }
        if (str_contains($name, 'face') || str_contains($name, 'biometric') || str_contains($name, 'zkteco') || str_contains($name, 'access controller') || str_contains($name, 'facial-recognition') || str_contains($name, 'access-control')) {
            return 'tr-access-control.jpg';
        }
        if (str_contains($name, 'camera') || str_contains($name, 'cctv') || str_contains($name, 'ip-cameras')) {
            return 'tr-cctv-bullet.jpg';
        }
        if (str_contains($name, 'ups') || str_contains($name, 'pdu') || str_contains($name, 'ap9641') || str_contains($name, 'ap8853')) {
            return 'tr-ups.jpg';
        }
        if (str_contains($name, 'poweredge') || str_contains($name, 'proliant') || str_contains($name, 'thinksystem') || str_contains($name, 'computing-office/desktops')) {
            return 'tr-server.jpg';
        }
        if (str_contains($name, 'ironwolf') || str_contains($name, 'red pro') || str_contains($name, 'nvme') || str_contains($name, 'hdd')) {
            return 'tr-hdd.jpg';
        }
        if (str_contains($name, 'nas') || str_contains($name, 'ds1825') || str_contains($name, 'ds925') || str_contains($name, 'rs3626') || str_contains($name, 'qnap')) {
            return 'tr-nas.jpg';
        }
        if (str_contains($name, 'pcie') || str_contains($name, 'sfp28') || str_contains($name, 'x710') || str_contains($name, 'xxv710') || str_contains($name, 'network adapter')) {
            return 'tr-nic.jpg';
        }
        if (str_contains($name, 'access point') || str_contains($name, 'eap') || str_contains($name, 'u7') || str_contains($name, 'gwn7670') || str_contains($name, 'wifi 7') || str_contains($name, 'wi-fi 7') || str_contains($name, 'access-points') || str_contains($name, 'rap73')) {
            return 'tr-wifi-ap.jpg';
        }
        if (str_contains($name, 'switch') || str_contains($name, 'crs') || str_contains($name, 'switches')) {
            return 'tr-switch.jpg';
        }
        if (str_contains($name, 'ccr') || str_contains($name, 'chateau')) {
            return 'tr-core-router.jpg';
        }
        if (str_contains($name, 'router') || str_contains($name, 'rut') || str_contains($name, 'trb') || str_contains($name, 'peplink') || str_contains($name, 'ur75') || str_contains($name, 'routers')) {
            return 'tr-5g-router.jpg';
        }

        return 'tr-laptop-14.jpg';
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function description(array $item): string
    {
        $parts = array_filter([
            trim((string) ($item['short_description'] ?? '')),
            ! empty($item['sales_angle']) ? 'Urban Focus use: '.$item['sales_angle'].'.' : null,
            'Configuration and lead time confirmed on quote. Price includes a buffer for Paystack and bank charges, plus a '.rtrim(rtrim(number_format($this->topupPercent(), 1), '0'), '.').'% catalogue top-up.',
        ]);

        return '<p>'.implode('</p><p>', array_map(fn (string $p) => e($p), $parts)).'</p>';
    }

    protected function warrantyMonths(string $path, string $name): int
    {
        $text = strtolower($path.' '.$name);

        if (str_contains($text, 'laptop') || str_contains($text, 'thinkpad') || str_contains($text, 'macbook') || str_contains($text, 'toughbook') || str_contains($path, 'warehouse')) {
            return 36;
        }

        if (str_contains($path, 'desktops') || str_contains($path, 'storage-devices') || str_contains($path, 'ups-systems')) {
            return 24;
        }

        if (str_contains($path, 'interactive-displays')) {
            return 24;
        }

        return 12;
    }

    protected function deliveryDays(string $path): int
    {
        if (str_contains($path, 'desktops') || str_contains($path, 'warehouse-technology')) {
            return 10;
        }

        return 7;
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

        $haystackWords = ' '.$this->wordString($haystack).' ';
        $needle = ' '.implode(' ', $words).' ';

        return str_contains($haystackWords, $needle);
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
        $candidate = $base;
        $suffix = 1;

        while (Product::withTrashed()->where('slug', $candidate)->exists()) {
            $skuSlug = Str::slug($this->normalizeCode($sku));
            $candidate = Str::limit($base.($skuSlug !== '' ? '-'.$skuSlug : '').($suffix > 1 ? '-'.$suffix : ''), 255, '');
            $suffix++;
        }

        return $candidate;
    }

    /** @return Collection<int, object> */
    protected function productIndex(): Collection
    {
        return $this->index ??= Product::withTrashed()
            ->get(['id', 'sku', 'model_number', 'name', 'brand', 'slug']);
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
        if ($row instanceof Product) {
            return $row;
        }

        return Product::withTrashed()->findOrFail($row->id);
    }
}
