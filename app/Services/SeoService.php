<?php

namespace App\Services;

use App\Models\Article;
use App\Models\Author;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

class SeoService
{
    public function sitemapXml(): string
    {
        return $this->rememberSitemap('sitemap.index.v1', function () {
            $sitemaps = [
                ['loc' => url('/sitemap-pages.xml')],
                ['loc' => url('/sitemap-products.xml')],
                ['loc' => url('/sitemap-images.xml')],
            ];

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
            foreach ($sitemaps as $sitemap) {
                $xml .= '<sitemap>';
                $xml .= '<loc>'.$this->xmlEscape((string) $sitemap['loc']).'</loc>';
                $xml .= '</sitemap>';
            }
            $xml .= '</sitemapindex>';

            return $xml;
        });
    }

    public function pagesSitemapXml(): string
    {
        return $this->rememberSitemap('sitemap.pages.v1', function () {
            $urls = $this->baseUrls();

            Category::where('is_active', true)->visibleInCatalog()->with('parent')->get()->each(function (Category $category) use (&$urls) {
                $urls[] = [
                    'loc' => $category->url(),
                    'lastmod' => $category->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => $category->parent_id ? '0.75' : '0.8',
                ];
            });

            return $this->buildUrlset($urls, includeImages: false);
        });
    }

    public function productsSitemapXml(): string
    {
        return $this->rememberSitemap('sitemap.products.v1', function () {
            $urls = [];

            Product::query()
                ->where('is_active', true)
                ->withoutDuplicateListings()
                ->with('images')
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (Product $product) use (&$urls) {
                    try {
                        $urls[] = [
                            'loc' => route('products.show', $product),
                            'lastmod' => $product->updated_at?->toAtomString(),
                            'changefreq' => 'weekly',
                            'priority' => '0.7',
                            'images' => $this->productImageEntries($product),
                        ];
                    } catch (\Throwable) {
                        // Skip broken product rows.
                    }
                });

            return $this->buildUrlset($urls, includeImages: true);
        });
    }

    public function imageSitemapXml(): string
    {
        return $this->rememberSitemap('sitemap.images.v3', function () {
            return $this->buildProductImageSitemap();
        });
    }

    public function robotsTxt(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'www.urbanfocus.co.za';
        $disallow = $this->robotsDisallowLines();
        $agents = array_values(array_unique(array_merge(
            ['*'],
            config('seo.ai_crawlers', [])
        )));

        $blocks = [];
        foreach ($agents as $agent) {
            $block = ['User-agent: '.$agent, 'Allow: /'];
            if ($keyFile = $this->indexNowKeyPath()) {
                $block[] = 'Allow: '.$keyFile;
            }
            $block[] = 'Allow: /llms.txt';
            $block = array_merge($block, $disallow);
            $blocks[] = implode("\n", $block);
        }

        $footer = [
            'Host: '.$host,
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return implode("\n\n", $blocks)."\n\n".implode("\n", $footer)."\n";
    }

    public function llmsTxt(): string
    {
        $entity = config('seo.entity', []);
        $name = $entity['legal_name'] ?? config('app.name', 'Urban Focus');
        $site = rtrim((string) ($entity['website'] ?? config('app.url')), '/');

        $lines = [
            '# '.$name,
            '',
            $entity['description'] ?? config('seo.defaults.description'),
            '',
            '> Website: '.$site.'/',
            '> Market: South Africa (nationwide delivery from Centurion, Gauteng)',
            '> Audience: '.($entity['audience'] ?? 'businesses, integrators, ISPs, schools and public-sector buyers'),
            '',
            '## What Urban Focus sells',
            '',
            '- Enterprise and SMB networking (Ubiquiti UniFi, MikroTik, TP-Link, fibre, PoE, switching)',
            '- Business laptops, desktops, servers and storage',
            '- CCTV, access control and security electronics',
            '- Specialist IT: hardware security keys, industrial IoT, private cloud and software licensing',
            '',
            '## Important URLs',
            '',
            '- Home: '.$site.'/',
            '- Shop: '.$site.'/shop',
            '- Brands: '.$site.'/brands',
            '- About: '.$site.'/about',
            '- Contact: '.$site.'/contact',
            '- Corporate procurement: '.$site.'/b2b/procurement',
            '- Request a quote: '.$site.'/b2b/quote',
            '- Knowledge Centre: '.$site.'/knowledge-centre',
            '- Sitemap: '.$site.'/sitemap.xml',
            '',
            'Prices are in South African Rand (ZAR) and include VAT where applicable.',
            'VAT invoices, nationwide courier delivery and formal quotations are available.',
        ];

        return implode("\n", $lines)."\n";
    }

    public function indexNowKeyPath(): ?string
    {
        $key = trim((string) config('seo.indexing.indexnow_key', ''));
        if ($key === '' || ! preg_match('/^[A-Za-z0-9\-]{8,128}$/', $key)) {
            return null;
        }

        return '/'.$key.'.txt';
    }

    /** @return list<string> */
    protected function robotsDisallowLines(): array
    {
        $lines = [];
        foreach (config('seo.robots_disallow', []) as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        foreach (app(SeoIndexPolicy::class)->robotsDisallowPaths() as $path) {
            if (! in_array('Disallow: '.$path, $lines, true)) {
                $lines[] = 'Disallow: '.$path;
            }
        }

        return $lines;
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

    /**
     * CollectionPage JSON-LD for category and shop listing pages.
     *
     * Must be emitted with `{!! json_encode(...) !!}` — Blade `{{ }}` HTML-escapes
     * quotes to `&quot;`, which makes the block unparsable in Search Console.
     *
     * @return array<string, mixed>
     */
    public function collectionPageSchema(
        string $name,
        string $url,
        ?string $description = null,
        ?LengthAwarePaginator $products = null,
    ): array {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $url,
        ];

        if (is_string($description) && trim($description) !== '') {
            $schema['description'] = $description;
        }

        if ($products instanceof LengthAwarePaginator && $products->count() > 0) {
            $position = (int) ($products->firstItem() ?? 1);
            $elements = [];

            foreach ($products as $product) {
                if (! $product instanceof Product) {
                    continue;
                }

                $elements[] = [
                    '@type' => 'ListItem',
                    'position' => $position++,
                    'url' => route('products.show', $product),
                ];
            }

            if ($elements !== []) {
                $schema['mainEntity'] = [
                    '@type' => 'ItemList',
                    'numberOfItems' => count($elements),
                    'itemListElement' => $elements,
                ];
            }
        }

        return $schema;
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
            'legalName' => config('seo.entity.legal_name', config('app.name')),
            'alternateName' => ['Urban Focus South Africa', 'Urban Focus IT'],
            'url' => rtrim((string) config('app.url'), '/'),
            'logo' => asset('images/logo-stacked.png'),
            'image' => asset('images/logo-stacked.png'),
            'description' => config('seo.entity.description', config('seo.defaults.description')),
            'email' => config('business.email'),
            'telephone' => '+'.ltrim((string) config('business.phone_tel'), '+'),
            'address' => $this->postalAddress(),
            'areaServed' => [
                '@type' => 'Country',
                'name' => 'South Africa',
            ],
            'knowsAbout' => [
                'Enterprise networking',
                'Ubiquiti UniFi',
                'MikroTik',
                'Business laptops',
                'CCTV',
                'IT procurement',
            ],
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => '+'.ltrim((string) config('business.phone_tel'), '+'),
                'email' => config('business.email'),
                'contactType' => 'sales',
                'areaServed' => 'ZA',
                'availableLanguage' => ['en', 'en-ZA'],
            ],
        ];

        if ($sameAs !== []) {
            $schema['sameAs'] = $sameAs;
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function brandSchema(Brand $brand): array
    {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'Brand',
            'name' => $brand->name,
            'url' => route('brands.show', $brand),
            'description' => $brand->seoDescription(),
        ];

        if ($brand->logo) {
            $schema['logo'] = asset($brand->logo);
        }

        if ($brand->website) {
            $schema['sameAs'] = [$brand->website];
        }

        return $schema;
    }

    /**
     * @return array<string, mixed>
     */
    public function aboutPageSchema(): array
    {
        $org = $this->organizationSchema();
        unset($org['@context']);

        return [
            '@context' => 'https://schema.org',
            '@type' => 'AboutPage',
            'name' => 'About Urban Focus',
            'url' => route('about'),
            'description' => config('seo.entity.description', config('seo.defaults.description')),
            'inLanguage' => 'en-ZA',
            'isPartOf' => [
                '@type' => 'WebSite',
                'name' => config('app.name'),
                'url' => rtrim((string) config('app.url'), '/'),
            ],
            'about' => $org,
            'mainEntity' => $org,
            'breadcrumb' => $this->breadcrumbSchema([
                ['name' => 'Home', 'url' => route('home')],
                ['name' => 'About Urban Focus', 'url' => route('about')],
            ]),
        ];
    }

    public function websiteSchema(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('app.name'),
            'url' => rtrim((string) config('app.url'), '/'),
            'description' => config('seo.entity.description', config('seo.defaults.description')),
            'inLanguage' => 'en-ZA',
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('app.name'),
                'url' => rtrim((string) config('app.url'), '/'),
            ],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => route('shop.index').'?q={search_term_string}',
                'query-input' => 'required name=search_term_string',
            ],
        ];
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
    /**
     * Canonical + rel prev/next for listing pages.
     *
     * Filter/sort/search query strings are omitted from the canonical so they
     * do not create duplicate indexable URLs. Pass $canonicalBase as the clean
     * category/brand/shop URL.
     */
    public function paginationMeta(LengthAwarePaginator $paginator, ?string $canonicalBase = null): array
    {
        $base = $canonicalBase ?: request()->url();
        $page = $paginator->currentPage();
        $canonical = $page <= 1
            ? $base
            : $base.(str_contains($base, '?') ? '&' : '?').'page='.$page;

        return [
            'canonical' => seo_canonical_url($canonical),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ];
    }

    public function solutionUrlForBrand(?Brand $brand): ?string
    {
        if (! $brand) {
            return null;
        }

        foreach (config('seo_landings', []) as $slug => $page) {
            if (! is_array($page)) {
                continue;
            }

            $slugs = array_filter(array_merge(
                [$page['brand_slug'] ?? null],
                $page['brand_slugs'] ?? []
            ));

            if (in_array($brand->slug, $slugs, true)) {
                return route('solutions.show', $slug);
            }
        }

        return null;
    }

    /** @return list<array{slug: string, h1: string}> */
    public function solutionsForBrand(?Brand $brand): array
    {
        if (! $brand) {
            return [];
        }

        $matches = [];

        foreach (config('seo_landings', []) as $slug => $page) {
            if (! is_array($page)) {
                continue;
            }

            $slugs = array_filter(array_merge(
                [$page['brand_slug'] ?? null],
                $page['brand_slugs'] ?? []
            ));

            if (in_array($brand->slug, $slugs, true)) {
                $matches[] = [
                    'slug' => $slug,
                    'h1' => $page['h1'] ?? $page['title'] ?? $slug,
                ];
            }
        }

        return $matches;
    }

    public function clearCache(): void
    {
        foreach ([
            'sitemap.xml',
            'sitemap-images.xml',
            'sitemap.main.v2',
            'sitemap.main.v3',
            'sitemap.main.v4',
            'sitemap.main.v5',
            'sitemap.main.v6',
            'sitemap.index.v1',
            'sitemap.pages.v1',
            'sitemap.products.v1',
            'sitemap.images.v2',
            'sitemap.images.v3',
        ] as $key) {
            Cache::forget($key);
        }

        foreach (glob(storage_path('app/sitemaps/*.xml')) ?: [] as $file) {
            @unlink($file);
        }

        Cache::forget('feeds.google-merchant.xml');
        Cache::forget('feeds.bobshop.xml');
        Cache::forget('feeds.bobshop.bulkload.csv');
        Cache::forget('feeds.pricecheck.csv');
        Cache::forget('feeds.pricecheck.xml');

        foreach (glob(storage_path('app/feeds/*.xml')) ?: [] as $file) {
            @unlink($file);
        }

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
    }

    /** @return list<array<string, string>> */
    protected function baseUrls(): array
    {
        $urls = [
            ['loc' => url('/'), 'changefreq' => 'daily', 'priority' => '1.0'],
            ['loc' => route('shop.index'), 'changefreq' => 'daily', 'priority' => '0.9'],
            ['loc' => route('contact'), 'changefreq' => 'monthly', 'priority' => '0.6'],
        ];

        foreach (['about', 'brands.index', 'shipping', 'returns', 'faq', 'warranty', 'popia', 'careers', 'privacy', 'terms', 'b2b.quote', 'b2b.rfq', 'b2b.procurement', 'b2b.source', 'blog.index'] as $page) {
            if (Route::has($page)) {
                $urls[] = ['loc' => route($page), 'changefreq' => 'monthly', 'priority' => '0.5'];
            }
        }

        if (Route::has('solutions.index')) {
            $urls[] = ['loc' => route('solutions.index'), 'changefreq' => 'weekly', 'priority' => '0.7'];
        }

        if (Route::has('case-studies.index')) {
            $urls[] = ['loc' => route('case-studies.index'), 'changefreq' => 'monthly', 'priority' => '0.4'];
            foreach (config('case_studies.items', []) as $study) {
                if (! is_array($study) || empty($study['slug']) || empty($study['published'])) {
                    continue;
                }
                $urls[] = [
                    'loc' => route('case-studies.show', $study['slug']),
                    'changefreq' => 'monthly',
                    'priority' => '0.45',
                ];
            }
        }

        if (Schema::hasTable('brands')) {
            Brand::where('is_active', true)->get()->each(function (Brand $brand) use (&$urls) {
                $urls[] = [
                    'loc' => route('brands.show', $brand),
                    'lastmod' => $brand->updated_at?->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.75',
                ];
            });
        }

        if (Schema::hasTable('articles')) {
            foreach (array_keys(config('blog.categories', [])) as $categoryKey) {
                $urls[] = [
                    'loc' => route('blog.category', $categoryKey),
                    'changefreq' => 'weekly',
                    'priority' => '0.55',
                ];
            }

            Article::published()->get()->each(function (Article $article) use (&$urls) {
                $urls[] = [
                    'loc' => route('blog.show', $article),
                    'lastmod' => $article->updated_at->toAtomString(),
                    'changefreq' => 'monthly',
                    'priority' => '0.6',
                ];
            });
        }

        if (Schema::hasTable('tags')) {
            Tag::query()
                ->whereHas('articles', fn ($q) => $q->published())
                ->get()
                ->each(function (Tag $tag) use (&$urls) {
                    $urls[] = [
                        'loc' => route('blog.tag', $tag),
                        'changefreq' => 'weekly',
                        'priority' => '0.5',
                    ];
                });
        }

        if (Schema::hasTable('authors')) {
            Author::where('is_active', true)
                ->whereHas('articles', fn ($q) => $q->published())
                ->get()
                ->each(function (Author $author) use (&$urls) {
                    $urls[] = [
                        'loc' => route('blog.author', $author),
                        'changefreq' => 'monthly',
                        'priority' => '0.5',
                    ];
                });
        }

        foreach (array_keys(config('seo_landings', [])) as $landingSlug) {
            $urls[] = [
                'loc' => route('solutions.show', $landingSlug),
                'changefreq' => 'monthly',
                'priority' => '0.7',
            ];
        }

        return $urls;
    }

    /** @return list<array{loc: string, title: string}> */
    protected function productImageEntries(Product $product): array
    {
        $images = [];
        $primary = $product->primary_image_url;

        if (is_string($primary) && $primary !== '') {
            $images[] = [
                'loc' => $primary,
                'title' => $this->safeImageTitle($product),
            ];
        }

        foreach ($product->images as $image) {
            $url = $image->url ?? null;
            if (! is_string($url) || $url === '' || $url === $primary) {
                continue;
            }

            $images[] = [
                'loc' => $url,
                'title' => $this->safeImageTitle($product),
            ];
        }

        return $images;
    }

    protected function buildProductImageSitemap(): string
    {
        $xmlns = ' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"';
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset'.$xmlns.'>';

        Product::query()
            ->where('is_active', true)
            ->withoutDuplicateListings()
            ->whereHas('images')
            ->with('images')
            ->orderBy('id')
            ->lazyById(200)
            ->each(function (Product $product) use (&$xml) {
                try {
                    $images = $this->productImageEntries($product);
                    if ($images === []) {
                        return;
                    }

                    $xml .= $this->buildUrlXml([
                        'loc' => route('products.show', $product),
                        'lastmod' => $product->updated_at?->toAtomString(),
                        'changefreq' => 'weekly',
                        'priority' => '0.6',
                        'images' => $images,
                    ], includeImages: true);
                } catch (\Throwable) {
                    // Skip broken product rows.
                }
            });

        $xml .= '</urlset>';

        return $xml;
    }

    protected function safeImageTitle(Product $product): string
    {
        try {
            return $product->imageAlt();
        } catch (\Throwable) {
            return $product->name ?: 'Urban Focus product';
        }
    }

    protected function rememberSitemap(string $key, callable $callback): string
    {
        $ttl = (int) config('seo.cache.sitemap_ttl', 3600);
        $path = storage_path('app/sitemaps/'.$key.'.xml');

        if (is_file($path) && (time() - filemtime($path)) < $ttl) {
            $cached = file_get_contents($path);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $xml = null;

        try {
            $xml = Cache::remember($key, $ttl, $callback);
        } catch (\Throwable $e) {
            report($e);
        }

        if (! is_string($xml) || $xml === '') {
            $xml = $callback();
        }

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }

        @file_put_contents($path, $xml);

        return $xml;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @param array<string, mixed> $url */
    protected function buildUrlXml(array $url, bool $includeImages = false): string
    {
        $xml = '<url>';
        $xml .= '<loc>'.$this->xmlEscape((string) $url['loc']).'</loc>';

        if (! empty($url['lastmod'])) {
            $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
        }

        $xml .= '<changefreq>'.$url['changefreq'].'</changefreq>';
        $xml .= '<priority>'.$url['priority'].'</priority>';

        if ($includeImages && ! empty($url['images'])) {
            foreach ($url['images'] as $image) {
                if (empty($image['loc'])) {
                    continue;
                }

                $xml .= '<image:image>';
                $xml .= '<image:loc>'.$this->xmlEscape((string) $image['loc']).'</image:loc>';
                $xml .= '<image:title>'.$this->xmlEscape((string) ($image['title'] ?? '')).'</image:title>';
                $xml .= '</image:image>';
            }
        }

        $xml .= '</url>';

        return $xml;
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
            $xml .= $this->buildUrlXml($url, $includeImages);
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
