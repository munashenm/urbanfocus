<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Response;

class FeedService
{
    public function googleMerchantXml(): string
    {
        $products = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->get();

        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
        $channel = $xml->addChild('channel');
        $channel->addChild('title', 'Urban Focus Products');
        $channel->addChild('link', config('app.url'));
        $channel->addChild('description', 'IT products from Urban Focus South Africa');

        foreach ($products as $product) {
            $item = $channel->addChild('item');
            $item->addChild('g:id', $product->sku ?: (string) $product->id, 'http://base.google.com/ns/1.0');
            $item->addChild('g:title', htmlspecialchars($product->name), 'http://base.google.com/ns/1.0');
            $item->addChild('g:description', htmlspecialchars(strip_tags($product->short_description ?: $product->description ?: '')), 'http://base.google.com/ns/1.0');
            $item->addChild('g:link', route('products.show', $product), 'http://base.google.com/ns/1.0');
            $item->addChild('g:image_link', $product->primary_image_url ?: '', 'http://base.google.com/ns/1.0');
            $item->addChild('g:availability', $product->isAvailable() ? 'in stock' : 'out of stock', 'http://base.google.com/ns/1.0');
            $item->addChild('g:price', number_format($product->effective_price, 2, '.', '').' ZAR', 'http://base.google.com/ns/1.0');
            $item->addChild('g:brand', htmlspecialchars($product->brand ?: 'Urban Focus'), 'http://base.google.com/ns/1.0');
            $item->addChild('g:condition', 'new', 'http://base.google.com/ns/1.0');
            if ($product->category) {
                $item->addChild('g:product_type', htmlspecialchars($product->category->name), 'http://base.google.com/ns/1.0');
            }
            if ($product->barcode) {
                $item->addChild('g:gtin', $product->barcode, 'http://base.google.com/ns/1.0');
            }
        }

        return $xml->asXML();
    }

    public function priceCheckCsv(): string
    {
        $products = Product::where('is_active', true)->get();
        $lines = ['SKU,Product Name,Brand,Price,Stock,URL,Category'];

        foreach ($products as $product) {
            $lines[] = implode(',', [
                $this->csvEscape($product->sku ?: $product->id),
                $this->csvEscape($product->name),
                $this->csvEscape($product->brand ?: 'Urban Focus'),
                number_format($product->effective_price, 2, '.', ''),
                $product->isAvailable() ? 'In Stock' : 'Out of Stock',
                route('products.show', $product),
                $this->csvEscape($product->category?->name ?? ''),
            ]);
        }

        return implode("\n", $lines);
    }

    protected function csvEscape(mixed $value): string
    {
        $value = (string) $value;
        if (str_contains($value, ',') || str_contains($value, '"')) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
