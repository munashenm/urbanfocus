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
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return implode("\n", $lines);
    }
}
