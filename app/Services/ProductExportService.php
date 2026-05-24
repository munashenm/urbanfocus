<?php

namespace App\Services;

use App\Models\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportService
{
    public function exportUrbanFocus(): StreamedResponse
    {
        $headers = [
            'ID', 'SKU', 'Name', 'Slug', 'Categories', 'Brand', 'Regular price', 'Sale price',
            'Stock', 'In stock?', 'Published', 'Short description', 'Description',
            'Meta title', 'Meta description', 'Meta keywords', 'Barcode', 'Google product category', 'Weight', 'URL',
        ];

        return $this->streamCsv('urbanfocus-products.csv', $headers, function ($handle) {
            Product::with('category')->orderBy('name')->chunk(200, function ($products) use ($handle) {
                foreach ($products as $product) {
                    fputcsv($handle, [
                        $product->id,
                        $product->sku,
                        $product->name,
                        $product->slug,
                        $product->category?->name,
                        $product->brand,
                        $product->price,
                        $product->sale_price,
                        $product->stock_quantity,
                        $product->in_stock ? 1 : 0,
                        $product->is_active ? 1 : 0,
                        $product->short_description,
                        strip_tags($product->description ?? ''),
                        $product->getAttributes()['meta_title'] ?? '',
                        $product->getAttributes()['meta_description'] ?? '',
                        $product->meta_keywords,
                        $product->barcode,
                        $product->google_product_category,
                        $product->weight,
                        route('products.show', $product),
                    ]);
                }
            });
        });
    }

    public function exportWooCommerce(): StreamedResponse
    {
        $headers = [
            'ID', 'Type', 'SKU', 'Name', 'Published', 'Categories', 'Regular price', 'Sale price',
            'In stock?', 'Stock', 'Short description', 'Description',
            'Meta: _yoast_wpseo_title', 'Meta: _yoast_wpseo_metadesc',
        ];

        return $this->streamCsv('woocommerce-export.csv', $headers, function ($handle) {
            Product::with('category')->orderBy('name')->chunk(200, function ($products) use ($handle) {
                foreach ($products as $product) {
                    fputcsv($handle, [
                        $product->woocommerce_id ?: $product->id,
                        'simple',
                        $product->sku,
                        $product->name,
                        $product->is_active ? 1 : 0,
                        $product->category?->name,
                        $product->price,
                        $product->sale_price,
                        $product->in_stock ? 1 : 0,
                        $product->stock_quantity,
                        $product->short_description,
                        $product->description,
                        $product->getAttributes()['meta_title'] ?? '',
                        $product->getAttributes()['meta_description'] ?? '',
                    ]);
                }
            });
        });
    }

    protected function streamCsv(string $filename, array $headers, callable $writer): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $writer) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, $headers);
            $writer($handle);
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
