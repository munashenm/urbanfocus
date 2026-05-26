<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class SeoService
{
    public function sitemapXml(): string
    {
        return Cache::remember('sitemap.xml', config('seo.cache.sitemap_ttl', 3600), function () {
            $urls = $this->baseUrls();

            Category::where('is_active', true)->visibleInCatalog()->get()->each(function (Category $category) use (&$urls) {
                $urls[] = [
                    'loc' => route('categories.show', $category),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

            Product::where('is_active', true)->with('images')->get()->each(function (Product $product) use (&$urls) {
                $entry = [
                    'loc' => route('products.show', $product),
                    'lastmod' => $product->updated_at->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                    'images' => $this->productImageEntries($product),
                ];
                $urls[] = $entry;
            });

            return $this->buildUrlset($urls, includeImages: true);
        });
    }

    public function imageSitemapXml(): string
    {
        return Cache::remember('sitemap-images.xml', config('seo.cache.sitemap_ttl', 3600), function () {
            $urls = [];

            Product::where('is_active', true)->with('images')->get()->each(function (Product $product) use (&$urls) {
                $images = $this->productImageEntries($product);
                if ($images === []) {
                    return;
                }

                $urls[] = [
                    'loc' => route('products.show', $product),
                    'lastmod' => $product->updated_at->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.6',
                    'images' => $images,
                ];
            });

            return $this->buildUrlset($urls, includeImages: true);
        });
    }

    public function robotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
        ];

        foreach (config('seo.robots_disallow', []) as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.url('/sitemap.xml');
        $lines[] = 'Sitemap: '.url('/sitemap-images.xml');

        return implode("\n", $lines);
    }

    /** @return list<array<string, mixed>> */
    public function breadcrumbSchema(array $items): array
    {
        $list = [];
        $position = 1;

        foreach ($items as $item) {
            $entry = [
                '@type' => 'ListItem',
                'position' => $position++,
                'name' => $item['name'],
            ];

            if (! empty($item['url'])) {
                $entry['item'] = $item['url'];
            }

            $list[] = $entry;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    public function organizationSchema(): array
    {
        $sameAs = array_values(array_filter([
            config('social.facebook'),
            config('social.instagram'),
            config('social.x'),
            config('social.tiktok'),
        ]));

        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'logo' => asset('images/logo-stacked.png'),
            'email' => config('business.email'),
            'telephone' => '+'.ltrim((string) config('business.phone_tel'), '+'),
            'address' => $this->postalAddress(),
        ];

        if ($sameAs !== []) {
            $schema['sameAs'] = $sameAs;
        }

        if ($vat = config('business.vat_number')) {
            $schema['taxID'] = $vat;
        }

        return $schema;
    }

    public function localBusinessSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'LocalBusiness',
            'name' => config('app.name'),
            'url' => config('app.url'),
            'logo' => asset('images/logo-stacked.png'),
            'image' => asset('images/logo-stacked.png'),
            'email' => config('business.email'),
            'telephone' => '+'.ltrim((string) config('business.phone_tel'), '+'),
            'address' => $this->postalAddress(),
            'areaServed' => array_map(fn (string $city) => [
                '@type' => 'City',
                'name' => $city,
                'containedInPlace' => ['@type' => 'Country', 'name' => 'South Africa'],
            ], config('seo.sa_cities', [])),
            'priceRange' => '$$',
            'openingHours' => config('business.hours'),
        ];
    }

    /** @return list<array{question: string, answer: string}> */
    public function faqSchema(array $faqs = []): array
    {
        $faqs = $faqs !== [] ? $faqs : config('seo.faq', []);

        if ($faqs === []) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq) => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $faq['answer'],
                ],
            ], $faqs),
        ];
    }

    /** @return array{canonical: string, prev: ?string, next: ?string} */
    public function paginationMeta(LengthAwarePaginator $paginator): array
    {
        $query = request()->except('page');
        $base = request()->url();
        $queryString = $query !== [] ? '?'.http_build_query($query) : '';

        $canonical = $paginator->currentPage() <= 1
            ? $base.$queryString
            : $base.$queryString.($queryString !== '' ? '&' : '?').'page='.$paginator->currentPage();

        return [
            'canonical' => $canonical,
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    public function clearCache(): void
    {
        foreach (['sitemap.xml', 'sitemap-images.xml'] as $key) {
            Cache::forget($key);
        }

        Cache::forget('feeds.google-merchant.xml');
        Cache::forget('feeds.pricecheck.csv');

        if (config('seo.indexing.ping_search_engines')) {
            $this->pingSearchEngines();
        }
    }

    public function pingSearchEngines(): void
    {
        $sitemap = url('/sitemap.xml');

        try {
            Http::timeout(5)->get('https://www.google.com/ping?sitemap='.urlencode($sitemap));
        } catch (\Throwable) {
            // Non-blocking.
        }

        try {
            Http::timeout(5)->get('https://www.bing.com/ping?sitemap='.urlencode($sitemap));
        } catch (\Throwable) {
            // Non-blocking.
        }

        $indexNowKey = config('seo.indexing.indexnow_key');
        if ($indexNowKey) {
            try {
                Http::timeout(5)->post('https://api.indexnow.org/indexnow', [
                    'host' => parse_url(config('app.url'), PHP_URL_HOST),
                    'key' => $indexNowKey,
                    'urlList' => [$sitemap, url('/')],
                ]);
            } catch (\Throwable) {
                // Non-blocking.
            }
        }
    }

    /** @return list<array<string, string>> */
    protected function baseUrls(): array
    {
        $urls = [
            ['loc' => url('/'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('shop.index'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('contact'), 'changefreq' => 'monthly', 'priority' => '0.6'],
        ];

        foreach (['about', 'brands.index', 'shipping', 'returns', 'warranty', 'popia', 'careers', 'privacy', 'terms', 'b2b.quote', 'b2b.rfq', 'b2b.procurement', 'b2b.source', 'blog.index', 'orders.track'] as $page) {
            $urls[] = ['loc' => route($page), 'changefreq' => 'monthly', 'priority' => '0.5'];
        }

        if (Schema::hasTable('brands')) {
            Brand::where('is_active', true)->get()->each(function (Brand $brand) use (&$urls) {
                $urls[] = [
                    'loc' => route('brands.show', $brand),
                    'changefreq' => 'weekly',
                    'priority' => '0.75',
                ];
            });
        }

        if (Schema::hasTable('articles')) {
            Article::published()->get()->each(function (Article $article) use (&$urls) {
                $urls[] = [
                    'loc' => route('blog.show', $article),
                    'lastmod' => $article->updated_at->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            });
        }

        return $urls;
    }

    /** @return list<array{loc: string, title: string}> */
    protected function productImageEntries(Product $product): array
    {
        $images = [];

        if ($product->primary_image_url) {
            $images[] = [
                'loc' => $product->primary_image_url,
                'title' => $product->imageAlt(),
            ];
        }

        foreach ($product->images as $image) {
            if ($image->url && $image->url !== $product->primary_image_url) {
                $images[] = [
                    'loc' => $image->url,
                    'title' => $product->imageAlt(),
                ];
            }
        }

        return $images;
    }

    /** @param list<array<string, mixed>> $urls */
    protected function buildUrlset(array $urls, bool $includeImages = false): string
    {
        $xmlns = $includeImages
            ? ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"'
            : ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset'.$xmlns.'>';

        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>'.htmlspecialchars($url['loc']).'</loc>';

            if (isset($url['lastmod'])) {
                $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
            }

            $xml .= '<changefreq>'.$url['changefreq'].'</changefreq>';
            $xml .= '<priority>'.$url['priority'].'</priority>';

            if ($includeImages && ! empty($url['images'])) {
                foreach ($url['images'] as $image) {
                    $xml .= '<image:image>';
                    $xml .= '<image:loc>'.htmlspecialchars($image['loc']).'</image:loc>';
                    $xml .= '<image:title>'.htmlspecialchars($image['title']).'</image:title>';
                    $xml .= '</image:image>';
                }
            }

            $xml .= '</url>';
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /** @return array<string, string> */
    protected function postalAddress(): array
    {
        return [
            '@type' => 'PostalAddress',
            'streetAddress' => trim(config('business.address.line1').' '.config('business.address.line2')),
            'addressLocality' => config('business.address.city'),
            'addressRegion' => config('business.address.province'),
            'postalCode' => config('business.address.postal_code'),
            'addressCountry' => config('seo.defaults.country', 'ZA'),
        ];
    }
}
