<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class FeedService
{
    /**
     * RSS 2.0 feed of published blog posts, served at /rss.xml.
     */
    public function blogRssXml(): string
    {
        return Cache::remember('feeds.blog-rss.xml', config('seo.cache.feed_ttl', 1800), function () {
            $articles = Article::published()
                ->with('author')
                ->orderByDesc('published_at')
                ->orderByDesc('created_at')
                ->limit(50)
                ->get();

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/"></rss>');
            $channel = $xml->addChild('channel');
            $channel->addChild('title', $this->xmlEscape('Urban Focus Blog'));
            $channel->addChild('link', config('app.url'));
            $channel->addChild('description', $this->xmlEscape('IT buying guides, comparisons and tech news for South African businesses.'));
            $channel->addChild('language', 'en-ZA');
            $channel->addChild('lastBuildDate', now()->toRfc822String());

            $atomLink = $channel->addChild('link', null, 'http://www.w3.org/2005/Atom');
            $atomLink->addAttribute('href', route('feeds.rss'));
            $atomLink->addAttribute('rel', 'self');
            $atomLink->addAttribute('type', 'application/rss+xml');

            foreach ($articles as $article) {
                $this->appendBlogItem($channel, $article);
            }

            return $xml->asXML();
        });
    }

    /**
     * Facebook Catalog (product) feed, served at /facebook-feed.xml.
     * Uses the RSS + g: namespace format Facebook Commerce Manager expects.
     */
    public function facebookCatalogXml(): string
    {
        return Cache::remember('feeds.facebook-catalog.xml', config('seo.cache.feed_ttl', 1800), function () {
            $products = Product::with(['category', 'images'])
                ->where('is_active', true)
                ->get();

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
            $channel = $xml->addChild('channel');
            $channel->addChild('title', $this->xmlEscape('Urban Focus Facebook Catalog'));
            $channel->addChild('link', config('app.url'));
            $channel->addChild('description', $this->xmlEscape('Product catalog for Facebook & Instagram Shops — Urban Focus South Africa.'));

            foreach ($products as $product) {
                if (! $product->isGoogleMerchantEligible()) {
                    continue;
                }

                $this->appendFacebookItem($channel, $product);
            }

            return $xml->asXML();
        });
    }

    public function googleMerchantXml(): string
    {
        return Cache::remember('feeds.google-merchant.xml', config('seo.cache.feed_ttl', 1800), function () {
            $products = Product::with(['category', 'images'])
                ->where('is_active', true)
                ->get();

            $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><rss version="2.0" xmlns:g="http://base.google.com/ns/1.0"></rss>');
            $channel = $xml->addChild('channel');
            $channel->addChild('title', 'Urban Focus Products');
            $channel->addChild('link', config('app.url'));
            $channel->addChild('description', 'IT products from Urban Focus South Africa');

            foreach ($products as $product) {
                if (! $product->isGoogleMerchantEligible()) {
                    continue;
                }

                $this->appendGoogleMerchantItem($channel, $product);
            }

            return $xml->asXML();
        });
    }

    public function priceCheckCsv(): string
    {
        return Cache::remember('feeds.pricecheck.csv', config('seo.cache.feed_ttl', 1800), function () {
            $lines = ['SKU,Product Name,Brand,Price,Stock,URL,Category'];

            Product::query()
                ->with('category')
                ->where('is_active', true)
                ->orderBy('id')
                ->chunkById(200, function ($products) use (&$lines) {
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
                });

            return implode("\n", $lines);
        });
    }

    /**
     * Bob Shop BulkLoad CSV (official template columns for Seller View upload).
     */
    public function bobShopBulkloadCsv(): string
    {
        return Cache::remember('feeds.bobshop.bulkload.csv', config('seo.cache.feed_ttl', 1800), function () {
            $handle = fopen('php://temp', 'r+');

            if ($handle === false) {
                throw new \RuntimeException('Could not build Bob Shop CSV.');
            }

            fputcsv($handle, config('bobshop.bulkload_headers', []));

            $listing = config('bobshop.listing', []);
            $timezone = config('app.timezone', 'Africa/Johannesburg');
            $start = now($timezone)->addDay()->setTime(
                (int) ($listing['start_hour'] ?? 1),
                (int) ($listing['start_minute'] ?? 0)
            );
            $stop = $start->copy()->addDays((int) ($listing['listing_days'] ?? 30));
            $dateFormat = 'd/m/Y H:i';

            Product::query()
                ->with(['category.parent.parent.parent', 'images'])
                ->where('is_active', true)
                ->orderBy('id')
                ->chunkById(100, function ($products) use ($handle, $listing, $start, $stop, $dateFormat) {
                    foreach ($products as $product) {
                        if (! $product->isBobShopBulkloadEligible()) {
                            continue;
                        }

                        fputcsv($handle, $this->bobShopBulkloadRow($product, $listing, $start, $stop, $dateFormat));
                    }
                });

            rewind($handle);
            $csv = stream_get_contents($handle);
            fclose($handle);

            return $csv !== false ? $csv : '';
        });
    }

    /**
     * @param  array<string, mixed>  $listing
     * @return list<string|int|float|null>
     */
    protected function bobShopBulkloadRow(Product $product, array $listing, \Carbon\Carbon $start, \Carbon\Carbon $stop, string $dateFormat): array
    {
        $quantity = $this->bobShopBulkloadQuantity($product);
        $buyNow = number_format($product->effective_price, 2, '.', '');
        $rrp = $product->is_on_sale
            ? number_format((float) $product->price, 2, '.', '')
            : '';

        $description = '<p>'.e($product->bobShopDescription()).'</p>'
            .'<p><a href="'.e(route('products.show', $product)).'">View on Urban Focus</a></p>';

        return [
            $listing['type'] ?? 'FIXED_PRICE',
            $product->googleFeedTitle(),
            $product->bobShopPrimaryCategoryId(),
            '',
            $listing['location'] ?? 'South Africa',
            Str::limit($product->sku, 100, ''),
            $start->format($dateFormat),
            $stop->format($dateFormat),
            (string) $quantity,
            '',
            '',
            '',
            '',
            $buyNow,
            $rrp,
            '',
            $listing['currency'] ?? 'R',
            $listing['condition'] ?? 'NEW',
            $this->bobShopBulkloadImageUrls($product),
            $listing['relist_option'] ?? 'RELIST_DAILY_ALL',
            (string) ($listing['relist_count'] ?? '1'),
            Str::limit($description, (int) config('bobshop.max_description_length', 8000), ''),
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            $product->bobShopWarrantyType(),
            $product->bobShopWarrantyRemarks(),
            '',
            '',
            '',
            '',
            '',
            $product->hasValidGtin() ? $product->normalizedGtin() : '',
            '',
            '',
            '',
            $product->weight ? number_format((float) $product->weight, 2, '.', '') : '',
            '',
            'End',
        ];
    }

    protected function bobShopBulkloadQuantity(Product $product): int
    {
        $qty = $product->bobShopStockQuantity();

        if ($qty > 0) {
            return $qty;
        }

        return max(0, (int) config('bobshop.bulkload.min_quantity', 1));
    }

    protected function bobShopBulkloadImageUrls(Product $product): string
    {
        $urls = [];

        if ($product->primary_image_url) {
            $urls[] = $product->primary_image_url;
        }

        foreach ($product->googleFeedAdditionalImages() as $imageUrl) {
            $urls[] = $imageUrl;
        }

        if ($urls === [] && config('bobshop.bulkload.use_placeholder_image', true)) {
            $urls[] = product_image_url();
        }

        return implode(':', array_values(array_unique($urls)));
    }

    /**
     * Bob Shop official trade feed XML (Bob-Shop-XML-Spec: ROOT > Products > Product).
     */
    public function bobShopXml(): string
    {
        return Cache::remember('feeds.bobshop.xml', config('seo.cache.feed_ttl', 1800), function () {
            $doc = new \DOMDocument('1.0', 'UTF-8');
            $doc->formatOutput = false;

            $root = $doc->createElement('ROOT');
            $doc->appendChild($root);

            if (config('bobshop.xml.include_version', true)) {
                $this->appendBobShopVersion($doc, $root);
            }

            $productsNode = $doc->createElement('Products');
            $root->appendChild($productsNode);

            Product::query()
                ->with(['category.parent.parent.parent', 'images'])
                ->where('is_active', true)
                ->orderBy('id')
                ->chunkById(100, function ($products) use ($doc, $productsNode) {
                    foreach ($products as $product) {
                        if (! $product->isBobShopEligible()) {
                            continue;
                        }

                        $this->appendBobShopProduct($doc, $productsNode, $product);
                    }
                });

            return $doc->saveXML();
        });
    }

    protected function appendBobShopVersion(\DOMDocument $doc, \DOMElement $root): void
    {
        $version = $doc->createElement('Version');
        $root->appendChild($version);

        $this->appendBobShopTextNode($doc, $version, 'PluginVersion', 'Urban Focus Laravel '.app()->version());
        $this->appendBobShopTextNode(
            $doc,
            $version,
            'ExportCreated',
            now(config('app.timezone', 'Africa/Johannesburg'))->toIso8601String()
        );
    }

    protected function appendBobShopProduct(\DOMDocument $doc, \DOMElement $productsNode, Product $product): void
    {
        $productNode = $doc->createElement('Product');
        $productsNode->appendChild($productNode);

        $this->appendBobShopCdataNode($doc, $productNode, 'ProductCode', Str::limit($product->sku, 100, ''));

        if ($product->hasValidGtin()) {
            $this->appendBobShopTextNode($doc, $productNode, 'ProductGTIN', $product->normalizedGtin());
        }

        $this->appendBobShopCdataNode($doc, $productNode, 'ProductName', $product->googleFeedTitle());
        $this->appendBobShopCdataNode($doc, $productNode, 'Category', $product->bobShopCategoryPath());
        $this->appendBobShopTextNode($doc, $productNode, 'Price', number_format($product->effective_price, 2, '.', ''));

        if ($product->is_on_sale) {
            $this->appendBobShopTextNode($doc, $productNode, 'MarketPrice', number_format((float) $product->price, 2, '.', ''));
        }

        $this->appendBobShopTextNode(
            $doc,
            $productNode,
            'AllowOffers',
            config('bobshop.xml.allow_offers', false) ? 'true' : 'false'
        );

        $this->appendBobShopTextNode($doc, $productNode, 'AvailableQty', (string) $product->bobShopStockQuantity());
        $this->appendBobShopTextNode($doc, $productNode, 'Condition', $product->bobShopCondition());

        $imageUrls = $product->bobShopXmlImageUrls();
        if ($imageUrls !== []) {
            $imagesNode = $doc->createElement('Images');
            $productNode->appendChild($imagesNode);

            foreach ($imageUrls as $imageUrl) {
                $this->appendBobShopCdataNode($doc, $imagesNode, 'ImageURL', $imageUrl);
            }
        }

        $this->appendBobShopCdataNode($doc, $productNode, 'ProductDescription', $product->bobShopXmlDescription());

        if ($product->brand) {
            $attributesNode = $doc->createElement('ProductAttributes');
            $productNode->appendChild($attributesNode);
            $this->appendBobShopTextNode($doc, $attributesNode, 'Brand', $product->brand);
        }

        $warrantyCode = $product->bobShopWarrantyTypeCode();
        $this->appendBobShopTextNode($doc, $productNode, 'WarrantyType', $warrantyCode);

        if ($warrantyCode !== '0') {
            $this->appendBobShopTextNode($doc, $productNode, 'WarrantyText', $product->bobShopWarrantyRemarks());
        }

        $shippingClass = trim((string) config('bobshop.xml.shipping_product_class', ''));
        if ($shippingClass !== '') {
            $this->appendBobShopTextNode($doc, $productNode, 'ShippingProductClass', $shippingClass);
        }

        $location = trim((string) config('bobshop.xml.location', ''));
        if ($location !== '') {
            $this->appendBobShopTextNode($doc, $productNode, 'Location', $location);
        }

        if ($product->weight) {
            $this->appendBobShopTextNode($doc, $productNode, 'ProductWeight', number_format((float) $product->weight, 2, '.', ''));
        }
    }

    protected function appendBobShopTextNode(\DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createTextNode($value));
        $parent->appendChild($node);
    }

    protected function appendBobShopCdataNode(\DOMDocument $doc, \DOMElement $parent, string $name, string $value): void
    {
        $node = $doc->createElement($name);
        $node->appendChild($doc->createCDATASection($value));
        $parent->appendChild($node);
    }

    protected function appendGoogleMerchantItem(\SimpleXMLElement $channel, Product $product): void
    {
        $ns = 'http://base.google.com/ns/1.0';
        $item = $channel->addChild('item');

        $this->addGoogleChild($item, 'id', $product->googleFeedId(), $ns);
        $this->addGoogleChild($item, 'title', $product->googleFeedTitle(), $ns);
        $this->addGoogleChild($item, 'description', $product->googleFeedDescription(), $ns);
        $this->addGoogleChild($item, 'link', route('products.show', $product), $ns);
        $this->addGoogleChild($item, 'image_link', $product->primary_image_url, $ns);

        foreach ($product->googleFeedAdditionalImages() as $imageUrl) {
            $this->addGoogleChild($item, 'additional_image_link', $imageUrl, $ns);
        }

        $this->addGoogleChild($item, 'availability', $product->isAvailable() ? 'in_stock' : 'out_of_stock', $ns);
        $this->addGoogleChild($item, 'condition', config('google-merchant.condition', 'new'), $ns);
        $this->addGoogleChild($item, 'brand', $product->brand ?: 'Urban Focus', $ns);

        if ($product->is_on_sale) {
            $this->addGoogleChild($item, 'price', $this->formatPrice($product->price), $ns);
            $this->addGoogleChild($item, 'sale_price', $this->formatPrice($product->sale_price), $ns);
        } else {
            $this->addGoogleChild($item, 'price', $this->formatPrice($product->price), $ns);
        }

        if ($category = $product->googleProductCategory()) {
            $this->addGoogleChild($item, 'google_product_category', $category, $ns);
        }

        if ($product->category) {
            $this->addGoogleChild($item, 'product_type', $product->category->name, $ns);
        }

        if ($product->hasValidGtin()) {
            $this->addGoogleChild($item, 'gtin', $product->normalizedGtin(), $ns);
        } elseif ($product->sku) {
            $this->addGoogleChild($item, 'mpn', $product->sku, $ns);
        } else {
            $this->addGoogleChild($item, 'identifier_exists', 'no', $ns);
        }

        if ($product->weight) {
            $this->addGoogleChild($item, 'shipping_weight', number_format((float) $product->weight, 2, '.', '').' kg', $ns);
        }

        $this->appendShipping($item, $ns);
        $this->appendReturnPolicy($item, $ns);
    }

    protected function appendReturnPolicy(\SimpleXMLElement $item, string $ns): void
    {
        $label = config('google-merchant.return_policy_label');

        if ($label) {
            $this->addGoogleChild($item, 'return_policy_label', $label, $ns);
        }
    }

    protected function appendShipping(\SimpleXMLElement $item, string $ns): void
    {
        $shipping = config('google-merchant.shipping');
        $price = $shipping['price'] ?? config('shipping.flat_rate', 0);

        $shippingNode = $item->addChild('shipping', null, $ns);
        $shippingNode->addChild('country', $shipping['country'] ?? 'ZA', $ns);
        $shippingNode->addChild('service', $shipping['service'] ?? 'Standard Courier', $ns);
        $shippingNode->addChild('price', $this->formatPrice((float) $price), $ns);
    }

    protected function appendBlogItem(\SimpleXMLElement $channel, Article $article): void
    {
        $url = route('blog.show', $article);
        $item = $channel->addChild('item');

        $item->addChild('title', $this->xmlEscape($article->title));
        $item->addChild('link', $url);
        $item->addChild('guid', $url)->addAttribute('isPermaLink', 'true');
        $item->addChild('description', $this->xmlEscape($article->seoDescription()));
        $item->addChild('pubDate', ($article->published_at ?? $article->created_at)->toRfc822String());
        $item->addChild('author', $this->xmlEscape($article->authorName()));

        if ($category = $article->categoryLabel()) {
            $item->addChild('category', $this->xmlEscape($category));
        }

        $contentNs = 'http://purl.org/rss/1.0/modules/content/';
        $body = $article->content ?: $article->excerpt ?: '';
        $encoded = $item->addChild('encoded', null, $contentNs);
        $node = dom_import_simplexml($encoded);
        $node->appendChild($node->ownerDocument->createCDATASection($body));
    }

    protected function appendFacebookItem(\SimpleXMLElement $channel, Product $product): void
    {
        $ns = 'http://base.google.com/ns/1.0';
        $item = $channel->addChild('item');

        $this->addGoogleChild($item, 'id', $product->googleFeedId(), $ns);
        $this->addGoogleChild($item, 'title', $product->googleFeedTitle(), $ns);
        $this->addGoogleChild($item, 'description', $product->googleFeedDescription(), $ns);
        $this->addGoogleChild($item, 'link', route('products.show', $product), $ns);
        $this->addGoogleChild($item, 'image_link', $product->primary_image_url, $ns);

        foreach ($product->googleFeedAdditionalImages() as $imageUrl) {
            $this->addGoogleChild($item, 'additional_image_link', $imageUrl, $ns);
        }

        // Facebook expects the space-separated availability vocabulary.
        $this->addGoogleChild($item, 'availability', $product->isAvailable() ? 'in stock' : 'out of stock', $ns);
        $this->addGoogleChild($item, 'condition', config('google-merchant.condition', 'new'), $ns);
        $this->addGoogleChild($item, 'price', $this->formatPrice((float) $product->price), $ns);

        if ($product->is_on_sale) {
            $this->addGoogleChild($item, 'sale_price', $this->formatPrice((float) $product->sale_price), $ns);
        }

        $this->addGoogleChild($item, 'brand', $product->brand ?: 'Urban Focus', $ns);

        if ($category = $product->googleProductCategory()) {
            $this->addGoogleChild($item, 'google_product_category', $category, $ns);
        }

        if ($product->category) {
            $this->addGoogleChild($item, 'product_type', $product->category->name, $ns);
        }

        if ($product->hasValidGtin()) {
            $this->addGoogleChild($item, 'gtin', $product->normalizedGtin(), $ns);
        } elseif ($product->sku) {
            $this->addGoogleChild($item, 'mpn', $product->sku, $ns);
        }
    }

    protected function xmlEscape(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', strip_tags($value)) ?? '';

        return htmlspecialchars(trim($value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected function addGoogleChild(\SimpleXMLElement $parent, string $name, string $value, string $ns): void
    {
        $parent->addChild($name, htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'), $ns);
    }

    protected function formatPrice(float $amount): string
    {
        return number_format($amount, 2, '.', '').' '.config('google-merchant.currency', 'ZAR');
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
