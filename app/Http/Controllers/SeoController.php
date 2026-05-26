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

    public function googleMerchantFeed(FeedService $feed): Response
    {
        return response($feed->googleMerchantXml(), 200, ['Content-Type' => 'application/xml']);
    }

    public function priceCheckFeed(FeedService $feed): Response
    {
        return response($feed->priceCheckCsv(), 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="urbanfocus-pricecheck.csv"',
        ]);
    }

    protected function emptySitemap(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"></urlset>';
    }
}
