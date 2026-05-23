<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WooCommerceImportService
{
    public function import(UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        $headers = fgetcsv($handle);
        $headers = array_map(fn ($h) => trim($h), $headers);

        $imported = 0;
        $updated = 0;
        $errors = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($headers)) {
                continue;
            }

            $data = array_combine($headers, $row);

            try {
                DB::transaction(function () use ($data, &$imported, &$updated) {
                    $result = $this->importRow($data);
                    $result === 'created' ? $imported++ : $updated++;
                });
            } catch (\Throwable $e) {
                $errors[] = ($data['Name'] ?? 'Unknown').': '.$e->getMessage();
            }
        }

        fclose($handle);

        return compact('imported', 'updated', 'errors');
    }

    protected function importRow(array $data): string
    {
        $wooId = $data['ID'] ?? null;
        $name = trim($data['Name'] ?? '');

        if (empty($name)) {
            throw new \InvalidArgumentException('Product name is required');
        }

        $categoryId = null;
        $categories = $data['Categories'] ?? '';
        if ($categories) {
            $categoryName = trim(explode(',', $categories)[0]);
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($categoryName)],
                ['name' => $categoryName, 'is_active' => true]
            );
            $categoryId = $category->id;
        }

        $regularPrice = (float) ($data['Regular price'] ?? 0);
        $salePrice = ! empty($data['Sale price']) ? (float) $data['Sale price'] : null;
        $stockQty = (int) ($data['Stock'] ?? 0);
        $inStock = strtolower($data['In stock?'] ?? '1') === '1';

        $attributes = [
            'category_id' => $categoryId,
            'name' => $name,
            'slug' => Str::slug($name),
            'sku' => $data['SKU'] ?: null,
            'short_description' => strip_tags($data['Short description'] ?? ''),
            'description' => $data['Description'] ?? '',
            'price' => $regularPrice,
            'sale_price' => $salePrice,
            'stock_quantity' => $stockQty,
            'manage_stock' => true,
            'in_stock' => $inStock,
            'is_active' => strtolower($data['Published'] ?? '1') === '1',
            'meta_title' => $data['Meta: _yoast_wpseo_title'] ?? null,
            'meta_description' => $data['Meta: _yoast_wpseo_metadesc'] ?? null,
        ];

        $product = Product::updateOrCreate(
            ['woocommerce_id' => $wooId ?: Str::slug($name)],
            $attributes
        );

        return $product->wasRecentlyCreated ? 'created' : 'updated';
    }
}
