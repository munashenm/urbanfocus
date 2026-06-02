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
        return response($seo->robotsTxt(), 200, ['Content-Type' => 'text/plain']);
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

    public function bobShopFeed(FeedService $feed): Response
    {
        try {
            return response($feed->bobShopXml(), 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
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

    protected function emptySitemap(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }
}
