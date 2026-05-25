<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\Social\SocialPostingService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportService
{
    public function __construct(
        protected ImageService $images,
        protected ProductPricingService $pricing,
    ) {}

    protected array $headerMap = [
        'id' => 'id',
        'sku' => 'sku',
        'productcode' => 'sku',
        'name' => 'name',
        'productname' => 'name',
        'categories' => 'categories',
        'category' => 'category',
        'categoryhead' => 'category_head',
        'images' => 'images',
        'image' => 'images',
        'regular price' => 'regular_price',
        'price' => 'regular_price',
        'sale price' => 'sale_price',
        'stock' => 'stock',
        'stock quantity' => 'stock',
        'availableqty' => 'stock',
        'in stock?' => 'in_stock',
        'published' => 'published',
        'short description' => 'short_description',
        'productsummary' => 'short_description',
        'description' => 'description',
        'productdescription' => 'description',
        'brand' => 'brand',
        'barcode' => 'barcode',
        'gtin' => 'barcode',
        'google product category' => 'google_product_category',
        'weight (kg)' => 'weight',
        'masskg' => 'weight',
        'meta: _yoast_wpseo_title' => 'meta_title',
        'meta: _yoast_wpseo_metadesc' => 'meta_description',
        'meta title' => 'meta_title',
        'meta description' => 'meta_description',
    ];

    public function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();

        if ($path === false) {
            throw new \RuntimeException('Could not read the uploaded CSV file.');
        }

        return $this->importFromPath($path);
    }

    public function importFromPath(string $path, ?callable $onProgress = null): array
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
        $errors = [];
        $rowNumber = 1;

        SocialPostingService::$suppress = true;

        try {
            while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                $rowNumber++;

                if ($this->isEmptyRow($row)) {
                    $skipped++;

                    continue;
                }

                $row = $this->normalizeRow($row, count($headers));
                $data = $this->normalizeImportData($this->mapRow($headers, $row));

                try {
                    DB::transaction(function () use ($data, &$imported, &$updated) {
                        $result = $this->importRow($data);
                        $result === 'created' ? $imported++ : $updated++;
                    });
                } catch (\InvalidArgumentException $e) {
                    if (str_starts_with($e->getMessage(), 'Skipped:')) {
                        $skippedNoImage++;

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
            SocialPostingService::$suppress = false;
        }

        fclose($handle);

        return compact('imported', 'updated', 'skipped', 'skippedNoImage', 'errors');
    }

    protected function normalizeImportData(array $data): array
    {
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

        if (empty($data['meta_title']) && ! empty($data['name'])) {
            $data['meta_title'] = Str::limit($data['name'].' | Urban Focus', 255, '');
        }

        if (empty($data['meta_description'])) {
            $source = strip_tags($data['short_description'] ?? $data['description'] ?? '');

            if ($source !== '') {
                $data['meta_description'] = Str::limit($source, 500, '');
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

    protected function importRow(array $data): string
    {
        $name = trim($data['name'] ?? '');

        if ($name === '') {
            throw new \InvalidArgumentException('Product name is required');
        }

        $wooId = $data['id'] ?? null;
        $sku = $data['sku'] ?? null;
        $existing = $this->findExisting($sku, $wooId);
        $imageUrls = $this->parseImageUrls($data['images'] ?? '');
        $hasExistingImages = $existing && $existing->images()->exists();

        if ($imageUrls === [] && ! $hasExistingImages) {
            throw new \InvalidArgumentException('Skipped: no product images in CSV');
        }

        $categoryId = $this->resolveCategoryId($data['categories'] ?? '');

        $costPrice = round($this->parsePrice($data['regular_price'] ?? '0'), 2);
        $regularPrice = $this->pricing->retailPrice($costPrice);
        $saleCost = round($this->parsePrice($data['sale_price'] ?? ''), 2);
        $salePrice = $saleCost > 0 ? $this->pricing->retailPrice($saleCost) : 0.0;
        $stockQty = (int) preg_replace('/\D/', '', $data['stock'] ?? '0');
        $inStockValue = strtolower($data['in_stock'] ?? '1');
        $inStock = in_array($inStockValue, ['1', 'yes', 'true', 'instock', 'in stock'], true);

        $slug = Str::slug($name);

        if ($existing) {
            $slug = $existing->slug;
        } elseif (Product::where('slug', $slug)->exists()) {
            $slug = $slug.'-'.Str::random(4);
        }

        $attributes = [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => $slug,
            'sku' => $sku ?: $existing?->sku,
            'short_description' => strip_tags($data['short_description'] ?? ''),
            'description' => $data['description'] ?? '',
            'cost_price' => $costPrice > 0 ? $costPrice : null,
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
            'woocommerce_id' => $wooId ?: ($existing?->woocommerce_id ?? $slug),
        ];

        if ($existing) {
            $existing->update($attributes);
            $product = $existing->fresh();
        } else {
            $product = Product::create($attributes);
        }

        if ($imageUrls !== []) {
            $this->importImages($product, $imageUrls);
        }

        if (! $product->images()->exists()) {
            throw new \InvalidArgumentException('Skipped: product has no images');
        }

        return $existing ? 'updated' : 'created';
    }

    protected function resolveCategoryId(string $categories): ?int
    {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\s*>\s*/', $categories) ?: [])));

        if ($parts === []) {
            return null;
        }

        $parentId = null;
        $category = null;
        $slugPrefix = '';

        foreach ($parts as $part) {
            $slug = Str::slug($slugPrefix.$part);
            $category = Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $part, 'is_active' => true, 'parent_id' => $parentId]
            );

            if ($category->parent_id !== $parentId) {
                $category->update(['parent_id' => $parentId]);
            }

            $parentId = $category->id;
            $slugPrefix = $slug.'-';
        }

        return $category?->id;
    }

    protected function findExisting(?string $sku, ?string $wooId): ?Product
    {
        if (! $sku && ! $wooId) {
            return null;
        }

        return Product::query()->where(function ($q) use ($sku, $wooId) {
            if ($sku) {
                $q->where('sku', $sku);
            }
            if ($wooId) {
                $q->orWhere('woocommerce_id', $wooId);
            }
        })->first();
    }

    /** @return list<string> */
    protected function parseImageUrls(string $value): array
    {
        if (trim($value) === '') {
            return [];
        }

        $urls = [];
        foreach (preg_split('/\s*,\s*/', $value) ?: [] as $url) {
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
