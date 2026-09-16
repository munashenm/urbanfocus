<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoImplementationTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_allows_catalogue_and_blocks_private_routes(): void
    {
        $body = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Sitemap:', $body);
        $this->assertStringContainsString('/sitemap.xml', $body);
        $this->assertStringContainsString('Disallow: /cart', $body);
        $this->assertStringContainsString('Disallow: /checkout', $body);
        $this->assertStringContainsString('Disallow: /admin', $body);
        $this->assertStringContainsString('Disallow: /api', $body);
        $this->assertStringContainsString('Disallow: /search', $body);
        $this->assertStringContainsString('User-agent: Googlebot', $body);
        $this->assertStringContainsString('User-agent: Bingbot', $body);
        $this->assertStringContainsString('User-agent: OAI-SearchBot', $body);
        $this->assertStringNotContainsString('Disallow: /products', $body);
        $this->assertStringNotContainsString('Disallow: /brands', $body);
        $this->assertStringNotContainsString('Disallow: /storage', $body);
    }

    public function test_blog_urls_redirect_to_knowledge_centre(): void
    {
        $this->get('/blog')->assertRedirect('/knowledge-centre');
    }

    public function test_knowledge_centre_and_case_studies_and_solutions_index_are_reachable(): void
    {
        $this->get('/knowledge-centre')->assertOk()->assertSee('Knowledge Centre', false);
        $this->get('/case-studies')->assertOk()->assertSee('Case studies coming soon', false);
        $this->get('/solutions')->assertOk()->assertSee('IT solutions for South African businesses', false);
        $this->get('/case-studies/not-a-real-study')->assertNotFound();
    }

    public function test_product_page_has_south_africa_title_canonical_and_product_json_ld(): void
    {
        $product = Product::factory()->create([
            'name' => 'Ubiquiti U7 Enterprise',
            'slug' => 'ubiquiti-u7-enterprise',
            'sku' => 'U7-Enterprise',
            'brand' => 'Ubiquiti',
            'price' => 8999,
        ]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        $this->assertStringContainsString('Ubiquiti U7 Enterprise | South Africa | Urban Focus', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"Product"/', $html);
        $this->assertMatchesRegularExpression('/"sku":\s*"U7-Enterprise"/', $html);
        $this->assertStringContainsString('Need 5 or more units?', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"Organization"/', $html);
        $this->assertStringContainsString('SKU: <strong>U7-Enterprise</strong>', $html);
        $this->assertStringContainsString('product:price:currency', $html);
        $this->assertStringContainsString('Ideal applications', $html);
        $this->assertMatchesRegularExpression('/"priceCurrency":\s*"ZAR"/', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"Offer"/', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"Brand"/', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"BreadcrumbList"/', $html);
    }

    public function test_sitemap_includes_public_pages_and_excludes_private_routes(): void
    {
        $product = Product::factory()->create([
            'name' => 'UniFi Switch Lite',
            'slug' => 'unifi-switch-lite',
            'is_active' => true,
        ]);

        $index = $this->get('/sitemap.xml')->assertOk()->getContent();
        $this->assertStringContainsString('sitemapindex', $index);
        $this->assertStringContainsString('/sitemap-pages.xml', $index);
        $this->assertStringContainsString('/sitemap-products.xml', $index);
        $this->assertStringNotContainsString('/admin', $index);

        $pages = $this->get('/sitemap-pages.xml')->assertOk()->getContent();
        $this->assertStringContainsString(route('about'), $pages);
        $this->assertStringContainsString(route('contact'), $pages);
        $this->assertStringContainsString(route('shop.index'), $pages);
        $this->assertStringNotContainsString('/cart', $pages);
        $this->assertStringNotContainsString('/checkout', $pages);
        $this->assertStringNotContainsString('/login', $pages);

        $products = $this->get('/sitemap-products.xml')->assertOk()->getContent();
        $this->assertStringContainsString(route('products.show', $product), $products);
    }

    public function test_llms_txt_describes_urban_focus(): void
    {
        $body = $this->get('/llms.txt')->assertOk()->getContent();

        $this->assertStringContainsString('Urban Focus', $body);
        $this->assertStringContainsString('https://www.urbanfocus.co.za', $body);
        $this->assertStringContainsString('/about', $body);
        $this->assertStringContainsString('South Africa', $body);
    }

    public function test_about_page_identifies_the_company_and_market(): void
    {
        $html = $this->get(route('about'))->assertOk()->getContent();

        $this->assertStringContainsString('Urban Focus', $html);
        $this->assertStringContainsString('https://www.urbanfocus.co.za/', $html);
        $this->assertStringContainsString('South African IT hardware', $html);
        $this->assertStringContainsString('rel="canonical"', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"AboutPage"/', $html);
    }

    public function test_indexnow_key_file_is_served_when_configured(): void
    {
        config(['seo.indexing.indexnow_key' => 'urbanfocus-indexnow-key']);

        $this->get('/urbanfocus-indexnow-key.txt')
            ->assertOk()
            ->assertSee('urbanfocus-indexnow-key', false);

        $this->get('/not-the-real-key.txt')->assertNotFound();
    }

    public function test_google_and_bing_verification_files_are_served_when_configured(): void
    {
        config([
            'seo.verification.bing' => 'bing-verify-token',
            'seo.verification.google_html_file' => 'google123abc.html',
        ]);

        $this->get('/BingSiteAuth.xml')
            ->assertOk()
            ->assertSee('bing-verify-token', false);

        $this->get('/google123abc.html')
            ->assertOk()
            ->assertSee('google-site-verification: google123abc.html', false);

        $this->get('/google999zzz.html')->assertNotFound();
    }

    public function test_indexnow_notifies_on_product_create_and_price_change(): void
    {
        config([
            'seo.indexing.indexnow_key' => 'urbanfocus-indexnow-key',
            'seo.indexing.indexnow_enabled' => true,
            'app.url' => 'https://www.urbanfocus.co.za',
        ]);

        \Illuminate\Support\Facades\Http::fake([
            'api.indexnow.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $product = Product::factory()->create([
            'name' => 'UniFi Gateway',
            'slug' => 'unifi-gateway',
            'price' => 4999,
        ]);

        $this->app->terminate();

        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($product) {
            $payload = $request->data();

            return str_contains($request->url(), 'api.indexnow.org/indexnow')
                && in_array(seo_canonical_url(route('products.show', $product)), $payload['urlList'] ?? [], true);
        });

        \Illuminate\Support\Facades\Http::fake([
            'api.indexnow.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $product->increment('views');
        $this->app->terminate();
        \Illuminate\Support\Facades\Http::assertNothingSent();

        \Illuminate\Support\Facades\Http::fake([
            'api.indexnow.org/*' => \Illuminate\Support\Facades\Http::response(['ok' => true], 200),
        ]);

        $product->update(['price' => 5299]);
        $this->app->terminate();

        \Illuminate\Support\Facades\Http::assertSent(function ($request) use ($product) {
            $payload = $request->data();

            return in_array(seo_canonical_url(route('products.show', $product)), $payload['urlList'] ?? [], true);
        });
    }

    public function test_canonical_helper_strips_tracking_parameters_and_uses_app_host(): void
    {
        config(['app.url' => 'https://www.urbanfocus.co.za']);

        $url = seo_canonical_url('https://urbanfocus.co.za/product/u7?utm_source=google&gclid=abc&page=2');

        $this->assertSame('https://www.urbanfocus.co.za/product/u7?page=2', $url);
        $this->assertStringNotContainsString('utm_source', $url);
        $this->assertStringNotContainsString('gclid', $url);
    }

    public function test_zero_price_products_are_not_merchant_eligible(): void
    {
        $product = Product::factory()->create([
            'name' => 'Enterprise Licensing',
            'price' => 0,
            'sku' => 'LIC-1',
            'brand' => 'Microsoft',
        ]);

        $this->assertContains('no_price', $product->googleMerchantIssues());
        $this->assertFalse($product->isGoogleMerchantEligible());
    }
}
