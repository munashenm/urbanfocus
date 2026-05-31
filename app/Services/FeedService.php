<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

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
        });
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
