<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductImportService
{
    protected array $headerMap = [
        'id' => 'id',
        'sku' => 'sku',
        'name' => 'name',
        'categories' => 'categories',
        'regular price' => 'regular_price',
        'sale price' => 'sale_price',
        'stock' => 'stock',
        'stock quantity' => 'stock',
        'in stock?' => 'in_stock',
        'published' => 'published',
        'short description' => 'short_description',
        'description' => 'description',
        'brand' => 'brand',
        'barcode' => 'barcode',
        'gtin' => 'barcode',
        'google product category' => 'google_product_category',
        'weight (kg)' => 'weight',
        'meta: _yoast_wpseo_title' => 'meta_title',
        'meta: _yoast_wpseo_metadesc' => 'meta_description',
        'meta title' => 'meta_title',
        'meta description' => 'meta_description',
    ];

    public function import(UploadedFile $file): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Could not read the uploaded CSV file.');
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
        $errors = [];

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->isEmptyRow($row)) {
                $skipped++;

                continue;
            }

            $row = $this->normalizeRow($row, count($headers));
            $data = $this->mapRow($headers, $row);

            try {
                DB::transaction(function () use ($data, &$imported, &$updated) {
                    $result = $this->importRow($data);
                    $result === 'created' ? $imported++ : $updated++;
                });
            } catch (\Throwable $e) {
                $errors[] = ($data['name'] ?? 'Unknown row').': '.$e->getMessage();
            }
        }

        fclose($handle);

        return compact('imported', 'updated', 'skipped', 'errors');
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

        $categoryId = null;
        $categories = $data['categories'] ?? '';
        if ($categories) {
            $categoryName = trim(explode(',', str_replace('>', ',', $categories))[0]);
            if ($categoryName) {
                $category = Category::firstOrCreate(
                    ['slug' => Str::slug($categoryName)],
                    ['name' => $categoryName, 'is_active' => true]
                );
                $categoryId = $category->id;
            }
        }

        $regularPrice = $this->parsePrice($data['regular_price'] ?? '0');
        $salePrice = $this->parsePrice($data['sale_price'] ?? '');
        $stockQty = (int) preg_replace('/\D/', '', $data['stock'] ?? '0');
        $inStockValue = strtolower($data['in_stock'] ?? '1');
        $inStock = in_array($inStockValue, ['1', 'yes', 'true', 'instock', 'in stock'], true);

        $slug = Str::slug($name);
        $wooId = $data['id'] ?? null;
        $sku = $data['sku'] ?? null;

        $existing = null;
        if ($sku || $wooId) {
            $existing = Product::query()->where(function ($q) use ($sku, $wooId) {
                if ($sku) {
                    $q->where('sku', $sku);
                }
                if ($wooId) {
                    $q->orWhere('woocommerce_id', $wooId);
                }
            })->first();
        }

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
            'price' => $regularPrice,
            'sale_price' => $salePrice > 0 ? $salePrice : null,
            'stock_quantity' => $stockQty,
            'manage_stock' => true,
            'in_stock' => $inStock || $stockQty > 0,
            'brand' => $data['brand'] ?? null,
            'barcode' => $data['barcode'] ?? null,
            'google_product_category' => $data['google_product_category'] ?? null,
            'weight' => isset($data['weight']) && $data['weight'] !== '' ? $this->parsePrice($data['weight']) : null,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'is_active' => in_array(strtolower($data['published'] ?? '1'), ['1', 'yes', 'true', 'publish'], true),
            'woocommerce_id' => $wooId ?: ($existing?->woocommerce_id ?? $slug),
        ];

        if ($existing) {
            $existing->update($attributes);

            return 'updated';
        }

        Product::create($attributes);

        return 'created';
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
