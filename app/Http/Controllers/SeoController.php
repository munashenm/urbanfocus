<?php

namespace App\Http\Controllers;

use App\Services\FeedService;
use App\Services\SeoService;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(SeoService $seo): Response
    {
        try {
            return response($seo->sitemapXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response($this->emptySitemapIndex(), 503, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }
    }

    public function pagesSitemap(SeoService $seo): Response
    {
        try {
            return response($seo->pagesSitemapXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response($this->emptySitemap(), 503, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }
    }

    public function productsSitemap(SeoService $seo): Response
    {
        try {
            return response($seo->productsSitemapXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response($this->emptySitemap(), 503, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }
    }

    public function imageSitemap(SeoService $seo): Response
    {
        try {
            return response($seo->imageSitemapXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response($this->emptySitemap(), 503, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }
    }

    public function robots(SeoService $seo): Response
    {
        return response($seo->robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function llms(SeoService $seo): Response
    {
        return response($seo->llmsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }

    public function indexNowKey(string $indexNowKey): Response
    {
        $expected = trim((string) config('seo.indexing.indexnow_key', ''));
        if (! $this->secureEquals($expected, $indexNowKey) || ! preg_match('/^[A-Za-z0-9\-]{8,128}$/', $expected)) {
            abort(404);
        }

        return response($expected, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function bingSiteAuth(): Response
    {
        $code = trim((string) config('seo.verification.bing', ''));
        if ($code === '') {
            abort(404);
        }

        $xml = '<?xml version="1.0"?>'."\n"
            .'<users>'."\n"
            .'    <user>'.htmlspecialchars($code, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</user>'."\n"
            .'</users>'."\n";

        return response($xml, 200, [
            'Content-Type' => 'text/xml; charset=UTF-8',
        ]);
    }

    public function googleHtmlVerification(string $googleFile): Response
    {
        $expected = trim((string) config('seo.verification.google_html_file', ''));
        if (! $this->secureEquals($expected, $googleFile)) {
            abort(404);
        }

        return response('google-site-verification: '.$expected."\n", 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }

    protected function secureEquals(string $expected, string $given): bool
    {
        return $expected !== ''
            && strlen($expected) === strlen($given)
            && hash_equals($expected, $given);
    }

    public function blogRss(FeedService $feed): Response
    {
        try {
            return response($feed->blogRssXml(), 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response($this->emptySitemap(), 503, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }
    }

    public function facebookCatalog(FeedService $feed): Response
    {
        try {
            return response($feed->facebookCatalogXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response($this->emptySitemap(), 503, ['Content-Type' => 'application/xml; charset=UTF-8']);
        }
    }

    public function googleMerchantFeed(FeedService $feed): Response
    {
        try {
            return response($feed->googleMerchantXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response('Feed temporarily unavailable.', 503, ['Content-Type' => 'text/plain']);
        }
    }

    public function bobShopXmlFeed(FeedService $feed): Response
    {
        try {
            return response($feed->bobShopXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response('Feed temporarily unavailable.', 503, ['Content-Type' => 'text/plain']);
        }
    }

    public function bobShopBulkloadCsv(FeedService $feed): Response
    {
        try {
            return response($feed->bobShopBulkloadCsv(), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="urbanfocus-bobshop-bulkload.csv"',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response('Feed temporarily unavailable.', 503, ['Content-Type' => 'text/plain']);
        }
    }

    public function priceCheckFeed(FeedService $feed): Response
    {
        try {
            return response($feed->priceCheckCsv(), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="urbanfocus-pricecheck.csv"',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response('Feed temporarily unavailable.', 503, ['Content-Type' => 'text/plain']);
        }
    }

    public function priceCheckXmlFeed(FeedService $feed): Response
    {
        try {
            return response($feed->priceCheckXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
        } catch (\Throwable $e) {
            report($e);

            return response('Feed temporarily unavailable.', 503, ['Content-Type' => 'text/plain']);
        }
    }

    protected function emptySitemap(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }

    protected function emptySitemapIndex(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></sitemapindex>';
    }
}
