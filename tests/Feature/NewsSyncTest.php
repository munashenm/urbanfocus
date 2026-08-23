<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Services\Blog\BlogInternalLinkingService;
use App\Services\NewsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_itweb_feed_uses_the_live_rss_url(): void
    {
        $itweb = collect(config('news.rss_feeds'))->firstWhere('name', 'ITWeb');

        $this->assertSame('https://www.itweb.co.za/rss', $itweb['url'] ?? null);
        $this->assertStringNotContainsString('/rss/articles', $itweb['url'] ?? '');
    }

    public function test_sync_imports_rss_items_without_missing_category_class(): void
    {
        Http::fake([
            'https://mybroadband.co.za/news/feed' => Http::response($this->rssXml('Fibre prices', 'https://mybroadband.test/fibre'), 200),
            'https://www.itweb.co.za/rss' => Http::response($this->rssXml('Cloud spend', 'https://itweb.test/cloud'), 200),
            'https://www.itweb.co.za/rss/articles' => Http::response('Not Found', 404),
            'https://techcentral.co.za/feed/' => Http::response($this->rssXml('Laptop demand', 'https://techcentral.test/laptops'), 200),
        ]);

        $result = app(NewsSyncService::class)->sync();

        $this->assertSame([], $result['errors'], implode(' | ', $result['errors']));
        $this->assertSame(3, $result['imported']);
        $this->assertSame(3, Article::count());
        $this->assertTrue(Article::where('category', 'news')->where('source_name', 'MyBroadband')->exists());
        $this->assertTrue(Article::where('source_name', 'ITWeb')->exists());
        $this->assertTrue(Article::where('source_name', 'TechCentral')->exists());
    }

    public function test_internal_linking_can_enrich_a_news_article(): void
    {
        $article = new Article([
            'title' => 'South Africa fibre prices drop',
            'slug' => 'south-africa-fibre-prices-drop',
            'content' => '<p>Business networking update for fibre and Wi-Fi.</p>',
            'category' => 'news',
        ]);

        $html = app(BlogInternalLinkingService::class)->enrich($article);

        $this->assertIsString($html);
        $this->assertStringContainsString('Business networking update', $html);
    }

    protected function rssXml(string $title, string $link): string
    {
        $safeTitle = htmlspecialchars($title, ENT_XML1);
        $safeLink = htmlspecialchars($link, ENT_XML1);

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test feed</title>
    <item>
      <title>{$safeTitle}</title>
      <link>{$safeLink}</link>
      <description>Short summary for {$safeTitle}.</description>
      <pubDate>Fri, 21 Aug 2026 12:00:00 +0200</pubDate>
    </item>
  </channel>
</rss>
XML;
    }
}
