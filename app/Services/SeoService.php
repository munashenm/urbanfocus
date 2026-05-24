<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;

class SeoService
{
    public function sitemapXml(): string
    {
        return Cache::remember('sitemap.xml', 3600, function () {
            $urls = [];

            $urls[] = [
                'loc' => url('/'),
                'changefreq' => 'daily',
                'priority' => '1.0',
            ];

            $urls[] = [
                'loc' => route('shop.index'),
                'changefreq' => 'daily',
                'priority' => '0.9',
            ];

            $urls[] = [
                'loc' => route('contact'),
                'changefreq' => 'monthly',
                'priority' => '0.6',
            ];

            foreach (['about', 'brands.index', 'shipping', 'returns', 'warranty', 'popia', 'careers', 'privacy', 'terms', 'b2b.quote', 'b2b.rfq', 'b2b.procurement', 'b2b.source', 'blog.index', 'orders.track'] as $page) {
                $urls[] = [
                    'loc' => route($page),
                    'changefreq' => 'monthly',
                    'priority' => '0.5',
                ];
            }

            if (class_exists(\App\Models\Brand::class)) {
                \App\Models\Brand::where('is_active', true)->get()->each(function ($brand) use (&$urls) {
                    $urls[] = [
                        'loc' => route('brands.show', $brand),
                        'changefreq' => 'weekly',
                        'priority' => '0.75',
                    ];
                });
            }

            if (class_exists(\App\Models\Article::class)) {
                \App\Models\Article::published()->get()->each(function ($article) use (&$urls) {
                    $urls[] = [
                        'loc' => route('blog.show', $article),
                        'lastmod' => $article->updated_at->toAtomString(),
                        'changefreq' => 'monthly',
                        'priority' => '0.6',
                    ];
                });
            }

            Category::where('is_active', true)->get()->each(function (Category $category) use (&$urls) {
                $urls[] = [
                    'loc' => route('categories.show', $category),
                    'changefreq' => 'weekly',
                    'priority' => '0.8',
                ];
            });

            Product::where('is_active', true)->get()->each(function (Product $product) use (&$urls) {
                $urls[] = [
                    'loc' => route('products.show', $product),
                    'lastmod' => $product->updated_at->toAtomString(),
                    'changefreq' => 'weekly',
                    'priority' => '0.7',
                ];
            });

            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';

            foreach ($urls as $url) {
                $xml .= '<url>';
                $xml .= '<loc>'.htmlspecialchars($url['loc']).'</loc>';
                if (isset($url['lastmod'])) {
                    $xml .= '<lastmod>'.$url['lastmod'].'</lastmod>';
                }
                $xml .= '<changefreq>'.$url['changefreq'].'</changefreq>';
                $xml .= '<priority>'.$url['priority'].'</priority>';
                $xml .= '</url>';
            }

            $xml .= '</urlset>';

            return $xml;
        });
    }

    public function robotsTxt(): string
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /cart',
            'Disallow: /checkout',
            'Disallow: /account',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /storage/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return implode("\n", $lines);
    }
}
