<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Marketing\MakeWebhookService;
use App\Services\Social\SocialPostingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportService
{
    public function __construct(
        protected ImageService $images,
        protected ProductPricingService $pricing,
        protected CatalogFilterService $catalogFilter,
        protected CategoryMapperService $categoryMapper,
    ) {}

    protected array $headerMap = [
        'id' => 'id',
        'sku' => 'sku',
        'productcode' => 'sku',
        'code' => 'sku',
        'name' => 'name',
        'productname' => 'name',
        'categories' => 'categories',
        'category' => 'category',
        'categoryhead' => 'category_head',
        'category head' => 'category_head',
        'images' => 'images',
        'image' => 'images',
        'image url' => 'images',
        'imageurl' => 'images',
        'product image' => 'images',
        'productimage' => 'images',
        'picture' => 'images',
        'thumbnail' => 'images',
        'regular price' => 'regular_price',
        'price' => 'regular_price',
        'list price' => 'list_price',
        'listprice' => 'list_price',
        'cost price' => 'cost_price',
        'costprice' => 'cost_price',
        'wholesale price' => 'cost_price',
        'sale price' => 'sale_price',
        'stock' => 'stock',
        'stock quantity' => 'stock',
        'availableqty' => 'stock',
        'qty' => 'stock',
        'quantity' => 'stock',
        'in stock?' => 'in_stock',
        'published' => 'published',
        'short description' => 'short_description',
        'productsummary' => 'short_description',
        'description' => 'description',
        'productdescription' => 'description',
        'brand' => 'brand',
        'manufacturer' => 'brand',
        'barcode' => 'barcode',
        'gtin' => 'barcode',
        'ean' => 'barcode',
        'google product category' => 'google_product_category',
        'weight (kg)' => 'weight',
        'masskg' => 'weight',
        'meta: _yoast_wpseo_title' => 'meta_title',
        'meta: _yoast_wpseo_metadesc' => 'meta_description',
        'meta title' => 'meta_title',
        'meta description' => 'meta_description',
        // Pinnacle distributor feed
        'stockcode' => 'sku',
        'prodname' => 'name',
        'prodimg' => 'images',
        'prodpriceexclvat' => 'regular_price',
        'prodqty' => 'stock',
        'category_tree' => 'category_tree',
        'barcodeean' => 'barcode',
        'barcodeupc' => 'barcode_upc',
        'topcat' => 'top_cat',
        'lastupdated' => 'last_updated',
        'prodexternalurl' => 'external_url',
        'srp price' => 'srp_price',
        'srp_price' => 'srp_price',
        // Scoop distributor pricelist
        'dealer price' => 'cost_price',
        'retail price' => 'list_price',
        'image url' => 'images',
        'total stock' => 'stock',
    ];

    public function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new \RuntimeException('Could not read the uploaded CSV file.');
        }

        return $this->importFromPath($path);
    }

    public function preview(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new \RuntimeException('Could not read the uploaded CSV file.');
        }

        return $this->previewFromPath($path);
    }

    public function importFromPath(string $path, ?callable $onProgress = null): array
    {
        return $this->processCsv($path, dryRun: false, onProgress: $onProgress);
    }

    public function previewFromPath(string $path, int $sampleLimit = 12): array
    {
        return $this->processCsv($path, dryRun: true, sampleLimit: $sampleLimit);
    }

    /** @param array<string, mixed> $data */
    public function normalizeImportRow(array $data): array
    {
        return $this->normalizeImportData($data);
    }

    protected function processCsv(string $path, bool $dryRun, ?callable $onProgress = null, int $sampleLimit = 12): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Could not read the CSV file.');
        }

        $firstLine = fgets($handle);
        rewind($handle);

        $delimiter = $this->detectDelimiter($firstLine ?: '');
        $headers = fgetcsv($handle, 0, $delimiter);

        if (! $headers) {
            fclose($handle);
            throw new \RuntimeException('CSV file is empty or invalid.');
        }

        $headers = $this->normalizeHeaders($headers);

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $skippedNoImage = 0;
        $skippedNoPrice = 0;
        $skippedImageFailed = 0;
        $skippedNonIt = 0;
        $errors = [];
        $samples = ['import' => [], 'skipped' => []];
        $rowNumber = 1;

        if (! $dryRun) {
            SocialPostingService::$suppress = true;
            MakeWebhookService::$suppress = true;
        }

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($this->isEmptyRow($row)) {
                    $skipped++;

                    continue;
                }

                $row = $this->normalizeRow($row, count($headers));
                $data = $this->normalizeImportData($this->mapRow($headers, $row));
                $evaluation = $this->evaluateRow($data);

                if ($evaluation['action'] === 'skip') {
                    $this->incrementSkipCounter(
                        $evaluation['reason'],
                        $skippedNonIt,
                        $skippedNoImage,
                        $skippedNoPrice,
                        $skippedImageFailed
                    );
                    $this->pushSample($samples['skipped'], $evaluation['name'], $evaluation['reason'], $sampleLimit);

                    continue;
                }

                if ($evaluation['action'] === 'error') {
                    $errors[] = ($data['name'] ?? "Row {$rowNumber}").': '.$evaluation['message'];

                    continue;
                }

                if ($dryRun) {
                    $evaluation['action'] === 'create' ? $imported++ : $updated++;
                    $this->pushSample(
                        $samples['import'],
                        $evaluation['name'],
                        $evaluation['action'],
                        $sampleLimit,
                        $evaluation['cost_price'],
                        $evaluation['retail_price']
                    );

                    continue;
                }

                try {
                    DB::transaction(function () use ($data, $evaluation, &$imported, &$updated, &$skippedImageFailed, &$skippedNoImage, $samples, $sampleLimit) {
                        $result = $this->importRow($data, $evaluation);
                        $result === 'created' ? $imported++ : $updated++;
                    });
                } catch (\InvalidArgumentException $e) {
                    if (str_starts_with($e->getMessage(), 'Skipped:')) {
                        $reason = $this->reasonFromSkipMessage($e->getMessage());
                        $this->incrementSkipCounter(
                            $reason,
                            $skippedNonIt,
                            $skippedNoImage,
                            $skippedNoPrice,
                            $skippedImageFailed
                        );
                        $this->pushSample($samples['skipped'], $data['name'] ?? 'Unknown', $reason, $sampleLimit);

                        continue;
                    }

                    $errors[] = ($data['name'] ?? "Row {$rowNumber}").': '.$e->getMessage();
                } catch (\Throwable $e) {
                    $errors[] = ($data['name'] ?? "Row {$rowNumber}").': '.$e->getMessage();
                }

                if ($onProgress && ($imported + $updated + $skippedNoImage) % 25 === 0) {
                    $onProgress($imported, $updated, $skippedNoImage, $rowNumber);
                }
            }
        } finally {
            if (! $dryRun) {
                SocialPostingService::$suppress = false;
                MakeWebhookService::$suppress = false;
            }
        }

        fclose($handle);

        $result = compact(
            'imported',
            'updated',
            'skipped',
            'skippedNoImage',
            'skippedNoPrice',
            'skippedImageFailed',
            'skippedNonIt',
            'errors'
        );

        if ($dryRun) {
            $result['total_rows'] = max(0, $rowNumber - 1);
            $result['would_create'] = $imported;
            $result['would_update'] = $updated;
            $result['pricing'] = $this->pricingPolicy();
            $result['samples'] = $samples;
            unset($result['imported'], $result['updated']);
        }

        return $result;
    }

    /** @return array{markup_percent: float, round_to: int, round_mode: string, low_cost_threshold: float, example: array{cost: float, retail: float}, low_cost_example: array{cost: float, retail: float}} */
    public function pricingPolicy(): array
    {
        $markup = (float) config('pricing.markup_percent', 40);
        $roundTo = (int) config('pricing.round_to', 50);
        $threshold = (float) config('pricing.low_cost_threshold', 20);
        $exampleCost = 100.0;
        $lowCostExample = max(1.0, $threshold > 0 ? $threshold - 1 : 4.0);
        $scoopExampleExVat = 475.0;

        return [
            'markup_percent' => $markup,
            'category_markups' => config('pricing.category_markups', []),
            'round_to' => $roundTo,
            'round_mode' => (string) config('pricing.round_mode', 'up'),
            'low_cost_threshold' => $threshold,
            'vat_rate' => (float) config('app.vat_rate', 15),
            'scoop_prices_ex_vat' => (bool) config('pricing.scoop_prices_ex_vat', true),
            'example' => [
                'cost' => $exampleCost,
                'retail' => $this->pricing->retailPrice($exampleCost),
            ],
            'low_cost_example' => [
                'cost' => $lowCostExample,
                'retail' => $this->pricing->retailPrice($lowCostExample),
            ],
            'scoop_example' => [
                'dealer_ex_vat' => $scoopExampleExVat,
                'cost_inc_vat' => $this->pricing->importCostPrice($scoopExampleExVat, 'scoop'),
                'retail' => $this->pricing->retailPriceForImport($scoopExampleExVat, 'scoop'),
            ],
        ];
    }

    /**
     * @return array{
     *     action: 'create'|'update'|'skip'|'error',
     *     reason?: string,
     *     message?: string,
     *     name?: string,
     *     cost_price?: float,
     *     retail_price?: float,
     *     existing?: Product|null
     * }
     */
    public function evaluateRow(array $data): array
    {
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            return ['action' => 'error', 'message' => 'Product name is required'];
        }

        if ($this->catalogFilter->isExcludedImportRow($data)) {
            return ['action' => 'skip', 'reason' => 'non_it', 'name' => $name];
        }

        if (($data['import_source'] ?? '') !== 'scoop') {
            $categories = trim($data['categories'] ?? '');
            if ($categories !== '' && $this->catalogFilter->isExcludedCategoryPath($categories)) {
                return ['action' => 'skip', 'reason' => 'non_it', 'name' => $name];
            }
        }

        $sku = $data['sku'] ?? null;
        $wooId = $data['id'] ?? null;
        $existing = $this->findExisting($sku, $wooId);
        $imageUrls = $this->parseImageUrls($data['images'] ?? '');
        $hasExistingImages = $existing && $existing->images()->exists();

        if ($imageUrls === [] && ! $hasExistingImages) {
            return ['action' => 'skip', 'reason' => 'no_image', 'name' => $name];
        }

        $costPrice = $this->resolveCostPrice($data);
        if ($costPrice <= 0) {
            return ['action' => 'skip', 'reason' => 'no_price', 'name' => $name];
        }

        $importSource = $data['import_source'] ?? null;
        $pricingContext = [
            'name' => $name,
            'brand' => $data['brand'] ?? null,
            'category_path' => $data['categories'] ?? '',
        ];
        $storedCost = $this->pricing->importCostPrice($costPrice, $importSource);
        $retailPrice = $this->pricing->retailPriceForImport($costPrice, $importSource, $pricingContext);
        if ($retailPrice <= 0) {
            return ['action' => 'skip', 'reason' => 'no_price', 'name' => $name];
        }

        return [
            'action' => $existing ? 'update' : 'create',
            'name' => $name,
            'cost_price' => $storedCost,
            'retail_price' => $retailPrice,
            'existing' => $existing,
        ];
    }

    protected function importRow(array $data, ?array $evaluation = null): string
    {
        $evaluation ??= $this->evaluateRow($data);

        if ($evaluation['action'] === 'skip' || $evaluation['action'] === 'error') {
            throw new \InvalidArgumentException('Skipped: '.$evaluation['reason']);
        }

        $name = $evaluation['name'];
        $existing = $evaluation['existing'];
        $wooId = $data['id'] ?? null;
        $sku = $data['sku'] ?? null;
        $imageUrls = $this->parseImageUrls($data['images'] ?? '');
        $categoryId = $this->resolveCategoryId($data['categories'] ?? '', $data['import_source'] ?? null);

        $costPrice = $evaluation['cost_price'];
        $regularPrice = $evaluation['retail_price'];
        $saleCost = round($this->parsePrice($data['sale_price'] ?? ''), 2);
        $importSource = $data['import_source'] ?? null;
        $salePrice = $saleCost > 0
            ? $this->pricing->retailPriceForImport($saleCost, $importSource, [
                'name' => $name,
                'brand' => $data['brand'] ?? null,
                'category_path' => $data['categories'] ?? '',
            ])
            : 0.0;
        $stockQty = (int) preg_replace('/\D/', '', $data['stock'] ?? '0');
        $inStockValue = strtolower($data['in_stock'] ?? '1');
        $inStock = in_array($inStockValue, ['1', 'yes', 'true', 'instock', 'in stock'], true);

        $slug = $this->resolveImportSlug($name, $sku, $existing);

        $attributes = [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku ?: $existing?->sku,
            'short_description' => strip_tags($data['short_description'] ?? ''),
            'description' => $data['description'] ?? '',
            'cost_price' => $costPrice,
            'price' => $regularPrice,
            'sale_price' => $salePrice > 0 && $salePrice < $regularPrice ? $salePrice : null,
            'stock_quantity' => $stockQty,
            'manage_stock' => true,
            'in_stock' => $inStock || $stockQty > 0,
            'brand' => $data['brand'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'google_product_category' => $data['google_product_category'] ?? null,
            'weight' => isset($data['weight']) && $data['weight'] !== '' ? round($this->parsePrice($data['weight']), 2) : null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
            'is_active' => in_array(strtolower($data['published'] ?? '1'), ['1', 'yes', 'true', 'publish'], true),
            'woocommerce_id' => $wooId ?: ($existing?->woocommerce_id ?? ($sku ?: $slug)),
        ];

        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            $existing->update($attributes);
            $product = $existing->fresh();
        } else {
            $product = Product::create($attributes);
        }

        if ($imageUrls !== []) {
            $saved = $this->importImages($product, $imageUrls);

            if ($saved === 0 && ! $product->images()->exists()) {
                throw new \InvalidArgumentException('Skipped: image download failed');
            }
        }

        if (! $product->images()->exists()) {
            throw new \InvalidArgumentException('Skipped: product has no images');
        }

        return $existing ? 'updated' : 'created';
    }

    protected function resolveCostPrice(array $data): float
    {
        foreach (['regular_price', 'cost_price', 'list_price'] as $field) {
            $price = round($this->parsePrice($data[$field] ?? ''), 2);

            if ($price > 0) {
                return $price;
            }
        }

        return 0.0;
    }

    protected function resolveCategoryId(string $categories, ?string $importSource = null): ?int
    {
        if (trim($categories) === '') {
            return null;
        }

        $scoopImport = $importSource === 'scoop';

        if (! $scoopImport && $this->catalogFilter->isExcludedCategoryPath($categories)) {
            return null;
        }

        $this->categoryMapper->ensureCanonicalTree();

        $categoryId = $this->categoryMapper->resolveCategoryId($categories);

        if ($categoryId !== null) {
            $category = Category::find($categoryId);

            if ($category && ! $scoopImport && $this->catalogFilter->isCategoryExcluded($category)) {
                return null;
            }

            return $categoryId;
        }

        return $this->resolveLegacyCategoryId($categories, $scoopImport);
    }

    protected function resolveLegacyCategoryId(string $categories, bool $scoopImport = false): ?int
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/', $categories) ?: [])));

        if ($parts === []) {
            return null;
        }

        $parentId = null;
        $category = null;
        $slugPrefix = '';

        foreach ($parts as $part) {
            if (! $scoopImport && $this->catalogFilter->isExcludedName($part)) {
                return null;
            }

            $slug = Str::slug($slugPrefix.$part);
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $part, 'is_active' => true, 'parent_id' => $parentId]
            );

            if ($category->parent_id !== $parentId) {
                $category->update(['parent_id' => $parentId]);
            }

            if (! $scoopImport && $this->catalogFilter->isCategoryExcluded($category)) {
                return null;
            }

            $parentId = $category->id;
            $slugPrefix = $slug.'-';
        }

        return $category?->id;
    }

    protected function incrementSkipCounter(
        string $reason,
        int &$skippedNonIt,
        int &$skippedNoImage,
        int &$skippedNoPrice,
        int &$skippedImageFailed
    ): void {
        match ($reason) {
            'non_it' => $skippedNonIt++,
            'no_image', 'no_images' => $skippedNoImage++,
            'no_price' => $skippedNoPrice++,
            'image_failed' => $skippedImageFailed++,
            default => $skippedNoImage++,
        };
    }

    protected function reasonFromSkipMessage(string $message): string
    {
        return match (true) {
            str_contains($message, 'non-IT') => 'non_it',
            str_contains($message, 'no product images') => 'no_image',
            str_contains($message, 'image download failed') => 'image_failed',
            str_contains($message, 'no images') => 'no_image',
            default => 'no_image',
        };
    }

    /** @param list<array<string, mixed>> $bucket */
    protected function pushSample(
        array &$bucket,
        string $name,
        string $label,
        int $limit,
        ?float $cost = null,
        ?float $retail = null
    ): void {
        if (count($bucket) >= $limit) {
            return;
        }

        $entry = ['name' => $name, 'label' => $label];

        if ($cost !== null) {
            $entry['cost'] = $cost;
            $entry['retail'] = $retail;
        }

        $bucket[] = $entry;
    }

    protected function normalizeImportData(array $data): array
    {
        if ($this->isPinnacleRow($data)) {
            $data = $this->normalizePinnacleRow($data);
        } elseif ($this->isScoopRow($data)) {
            $data = $this->normalizeScoopRow($data);
        } elseif ($this->isAstrumRow($data)) {
            $data = $this->normalizeAstrumRow($data);
        }

        if (! empty($data['sku'])) {
            $data['sku'] = $this->normalizeSku($data['sku']);
        }

        if (empty($data['categories'])) {
            $head = trim($data['category_head'] ?? '');
            $sub = trim($data['category'] ?? '');

            if ($head && $sub) {
                $data['categories'] = $head.' > '.$sub;
            } elseif ($head) {
                $data['categories'] = $head;
            } elseif ($sub) {
                $data['categories'] = $sub;
            }
        }

        if (($data['import_source'] ?? '') === 'scoop' || trim($data['categories'] ?? '') !== '') {
            $data['categories'] = $this->categoryMapper->mapImportCategories($data);
        }

        if (empty($data['meta_title']) && ! empty($data['name'])) {
            $data['meta_title'] = Str::limit($data['name'].' | Urban Focus', 255, '');
        }

        if (empty($data['meta_description'])) {
            $source = strip_tags($data['short_description'] ?? $data['description'] ?? '');

            if ($source === '' && ! empty($data['name'])) {
                $source = (! empty($data['brand']) ? 'Buy '.$data['brand'].' ' : '').$data['name'];
            }

            if ($source !== '') {
                $data['meta_description'] = seo_meta_description($source, [
                    'type' => 'product',
                    'name' => $data['name'] ?? '',
                    'brand' => $data['brand'] ?? null,
                    'category' => $data['category'] ?? null,
                ]);
            }
        }

        if (empty($data['meta_keywords'])) {
            $keywords = array_filter([
                $data['brand'] ?? null,
                $data['category_head'] ?? null,
                $data['category'] ?? null,
            ]);
            if ($keywords !== []) {
                $data['meta_keywords'] = Str::limit(implode(', ', $keywords), 255, '');
            }
        }

        return $data;
    }

    protected function isScoopRow(array $data): bool
    {
        if (trim($data['category_tree'] ?? '') !== '') {
            return false;
        }

        if (($data['import_source'] ?? '') === 'scoop') {
            return true;
        }

        if ($this->isAstrumRow($data)) {
            return false;
        }

        $images = trim($data['images'] ?? '');

        return trim($data['sku'] ?? '') !== ''
            && (trim($data['name'] ?? '') !== '' || trim($data['description'] ?? '') !== '')
            && round($this->parsePrice($data['cost_price'] ?? ''), 2) > 0
            && $images !== ''
            && str_contains(strtolower($images), 'scoop.co.za')
            && trim($data['category'] ?? '') === ''
            && trim($data['category_head'] ?? '') === ''
            && trim($data['srp_price'] ?? '') === '';
    }

    /** @param array<string, mixed> $data */
    protected function normalizeScoopRow(array $data): array
    {
        $data['import_source'] = 'scoop';

        if (trim($data['name'] ?? '') === '' && trim($data['description'] ?? '') !== '') {
            $data['name'] = trim($data['description']);
        }

        if (trim($data['short_description'] ?? '') === '' && trim($data['name'] ?? '') !== '') {
            $data['short_description'] = Str::limit(trim($data['name']), 255, '');
        }

        if (! isset($data['in_stock']) || trim((string) $data['in_stock']) === '') {
            $qty = (int) preg_replace('/\D/', '', (string) ($data['stock'] ?? '0'));
            $data['in_stock'] = $qty > 0 ? '1' : '0';
        }

        if (! isset($data['published']) || trim((string) $data['published']) === '') {
            $data['published'] = '1';
        }

        return $data;
    }

    protected function isAstrumRow(array $data): bool
    {
        if (trim($data['category_tree'] ?? '') !== '') {
            return false;
        }

        if (($data['import_source'] ?? '') === 'astrum') {
            return true;
        }

        return trim($data['sku'] ?? '') !== ''
            && trim($data['name'] ?? '') !== ''
            && trim($data['category'] ?? '') !== ''
            && trim($data['images'] ?? '') === ''
            && trim($data['category_head'] ?? '') === ''
            && (trim($data['srp_price'] ?? '') !== '' || $this->resolveCostPrice($data) > 0);
    }

    /** @param array<string, mixed> $data */
    protected function normalizeAstrumRow(array $data): array
    {
        $data['import_source'] = 'astrum';
        $data['category'] = html_entity_decode(trim($data['category'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (trim($data['brand'] ?? '') === '') {
            $data['brand'] = 'Astrum';
        }

        if (trim($data['short_description'] ?? '') === '' && $data['category'] !== '') {
            $data['short_description'] = 'Astrum '.$data['category'];
        }

        if (! isset($data['in_stock']) || trim((string) $data['in_stock']) === '') {
            $qty = (int) preg_replace('/\D/', '', (string) ($data['stock'] ?? '0'));
            $data['in_stock'] = $qty > 0 ? '1' : '0';
        }

        if (! isset($data['published']) || trim((string) $data['published']) === '') {
            $data['published'] = '1';
        }

        return $data;
    }

    protected function isPinnacleRow(array $data): bool
    {
        return trim($data['category_tree'] ?? '') !== '';
    }

    /** @param array<string, mixed> $data */
    protected function normalizePinnacleRow(array $data): array
    {
        $data['import_source'] = 'pinnacle';

        if (trim($data['barcode'] ?? '') === '') {
            $barcode = trim($data['barcode_upc'] ?? '');
            $data['barcode'] = preg_replace('/\D/', '', $barcode) ?: $barcode;
        } else {
            $data['barcode'] = preg_replace('/\D/', '', $data['barcode']) ?: $data['barcode'];
        }

        if (trim($data['categories'] ?? '') === '' && trim($data['category_tree'] ?? '') !== '') {
            $data['categories'] = $this->formatPinnacleCategoryTree($data['category_tree']);
            $root = $this->catalogFilter->pinnacleTreeRoot($data['category_tree']);
            $data['category_head'] = $root ? ucwords(str_replace('-', ' ', $root)) : '';
        }

        if (trim($data['short_description'] ?? '') === '') {
            $data['short_description'] = $this->buildPinnacleShortDescription($data);
        }

        if (! isset($data['in_stock']) || trim((string) $data['in_stock']) === '') {
            $qty = (int) preg_replace('/\D/', '', (string) ($data['stock'] ?? '0'));
            $data['in_stock'] = $qty > 0 ? '1' : '0';
        }

        if (! isset($data['published']) || trim((string) $data['published']) === '') {
            $data['published'] = '1';
        }

        return $data;
    }

    protected function formatPinnacleCategoryTree(string $tree): string
    {
        $parts = array_values(array_filter(array_map('trim', explode('/', strtolower($tree)))));

        return implode(' > ', array_map(
            fn (string $segment) => ucwords(str_replace('-', ' ', $segment)),
            $parts
        ));
    }

    /** @param array<string, mixed> $data */
    protected function buildPinnacleShortDescription(array $data): string
    {
        $parts = [];

        if ($topCat = trim($data['top_cat'] ?? '')) {
            $parts[] = $topCat;
        }

        for ($i = 1; $i <= 6; $i++) {
            $option = trim($data["highlight_feature_{$i}_option"] ?? '');
            $value = trim($data["highlight_feature_{$i}_value"] ?? '');

            if ($option !== '' && $value !== '') {
                $parts[] = $option.': '.$value;
            }
        }

        return implode('. ', $parts);
    }

    protected function normalizeSku(string $sku): string
    {
        $sku = trim($sku);

        if (preg_match('/^="?(.+?)"?$/', $sku, $matches)) {
            return trim($matches[1]);
        }

        return trim($sku, "=\"");
    }

    protected function detectDelimiter(string $line): string
    {
        $semicolons = substr_count($line, ';');
        $commas = substr_count($line, ',');

        return $semicolons > $commas ? ';' : ',';
    }

    protected function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header) {
            $header = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header);
            $header = trim($header);

            return $this->headerMap[strtolower($header)] ?? Str::slug($header, '_');
        }, $headers);
    }

    protected function mapRow(array $headers, array $row): array
    {
        $mapped = [];
        foreach ($headers as $index => $key) {
            $mapped[$key] = trim($row[$index] ?? '');
        }

        return $mapped;
    }

    protected function normalizeRow(array $row, int $headerCount): array
    {
        if (count($row) < $headerCount) {
            return array_pad($row, $headerCount, '');
        }

        if (count($row) > $headerCount) {
            return array_slice($row, 0, $headerCount);
        }

        return $row;
    }

    protected function isEmptyRow(array $row): bool
    {
        return count(array_filter(array_map('trim', $row))) === 0;
    }

    protected function findExisting(?string $sku, ?string $wooId): ?Product
    {
        if (! $sku && ! $wooId) {
            return null;
        }

        return Product::withTrashed()->where(function ($q) use ($sku, $wooId) {
            if ($sku) {
                $q->where('sku', $sku);
            }
            if ($wooId) {
                $q->orWhere('woocommerce_id', $wooId);
            }
        })->first();
    }

    protected function resolveImportSlug(string $name, ?string $sku, ?Product $existing): string
    {
        if ($existing) {
            return $existing->slug;
        }

        $base = Str::slug($name) ?: 'product';
        $slug = $base;

        if ($sku && $this->importSlugTaken($slug)) {
            $skuSlug = Str::slug($this->normalizeSku($sku));

            if ($skuSlug !== '') {
                $slug = Str::limit($base.'-'.$skuSlug, 255, '');
            }
        }

        $attempt = 0;

        while ($this->importSlugTaken($slug)) {
            $attempt++;
            $suffix = $sku
                ? Str::slug($this->normalizeSku($sku)).'-'.$attempt
                : (string) $attempt;
            $slug = Str::limit($base.'-'.$suffix, 255, '');
        }

        return $slug;
    }

    protected function importSlugTaken(string $slug): bool
    {
        return Product::withTrashed()->where('slug', $slug)->exists();
    }

    /** @return list<string> */
    protected function parseImageUrls(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $urls = [];
        foreach (preg_split('/\s*[|,]\s*/', $value) ?: [] as $url) {
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            if (! str_starts_with($url, 'http://') && ! str_starts_with($url, 'https://')) {
                continue;
            }
            $urls[] = $url;
        }

        return array_values(array_unique($urls));
    }

    protected function importImages(Product $product, array $urls): int
    {
        $saved = 0;
        $sortOrder = (int) ($product->images()->max('sort_order') ?? 0);

        foreach ($urls as $url) {
            $path = $this->images->storeProductImageFromUrl($url, $product->id);
            if (! $path) {
                continue;
            }

            ProductImage::create([
                'product_id' => $product->id,
                'path' => $path,
                'alt_text' => $product->name,
                'sort_order' => ++$sortOrder,
                'is_primary' => $product->images()->count() === 0,
            ]);
            $saved++;
        }

        return $saved;
    }

    protected function parsePrice(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            return 0.0;
        }

        $value = preg_replace('/[^\d.,-]/', '', $value);
        $value = str_replace(',', '.', $value);

        return (float) $value;
    }
}
