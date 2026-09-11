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
