<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\CategorySlugRedirect;
use App\Services\CategoryMapperService;
use App\Services\CategorySlugRedirectCleaner;
use App\Services\SeoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class StorefrontQaFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_review_badge_stays_hidden_without_real_review_data(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('Trusted by Businesses Across South Africa', $html);
        $this->assertStringContainsString('Urban Focus consistently delivers genuine networking gear', $html);
        $this->assertStringNotContainsString('google-reviews-bar', $html);
        $this->assertStringNotContainsString('Google Reviews', $html);
        $this->assertStringNotContainsString('AggregateRating', $html);
        $this->assertStringNotContainsString('ratingValue', $html);
        $this->assertStringNotContainsString('reviewCount', $html);
    }

    public function test_google_review_badge_renders_only_for_explicit_live_values(): void
    {
        config([
            'trust.google_reviews.enabled' => true,
            'trust.google_reviews.rating' => '4.8',
            'trust.google_reviews.count' => '50',
            'trust.google_reviews.url' => 'https://g.page/r/urbanfocus/review',
        ]);

        $placeholder = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Google Reviews', $placeholder);

        config([
            'trust.google_reviews.enabled' => true,
            'trust.google_reviews.rating' => '4.9',
            'trust.google_reviews.count' => '120',
            'trust.google_reviews.url' => 'https://search.google.com/local/writereview?placeid=ChIJrealPlace',
        ]);

        $live = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('Google Reviews', $live);
        $this->assertStringContainsString('4.9', $live);
        $this->assertStringContainsString('120+ reviews', $live);
        $this->assertStringContainsString('https://search.google.com/local/writereview?placeid=ChIJrealPlace', $live);
        $this->assertStringNotContainsString('AggregateRating', $live);
    }

    public function test_placeholder_verification_and_pixels_are_omitted_and_ga4_stays(): void
    {
        config([
            'seo.analytics.gtm_id' => '',
            'seo.analytics.ga4_id' => 'G-7V253452HR',
            'seo.analytics.meta_pixel_id' => '123456789',
            'seo.analytics.tiktok_pixel_id' => 'XXXXXXXX',
            'seo.verification.google' => 'your-code',
            'seo.verification.bing' => 'your-code',
        ]);

        $html = $this->get(route('contact'))->assertOk()->getContent();

        $this->assertStringNotContainsString('google-site-verification', $html);
        $this->assertStringNotContainsString('msvalidate.01', $html);
        $this->assertStringNotContainsString('your-code', $html);
        $this->assertStringNotContainsString('fbq(', $html);
        $this->assertStringNotContainsString('123456789', $html);
        $this->assertStringNotContainsString('ttq.load', $html);
        $this->assertStringNotContainsString('XXXXXXXX', $html);
        $this->assertStringContainsString('G-7V253452HR', $html);
        $this->assertStringContainsString('gtag/js?id=G-7V253452HR', $html);

        config([
            'seo.verification.google' => 'google-real-token-abc',
            'seo.verification.bing' => 'bing-real-token-def',
            'seo.analytics.meta_pixel_id' => '109876543210987',
            'seo.analytics.tiktok_pixel_id' => 'C9A1B2C3D4E5F6',
            'seo.analytics.ga4_id' => 'G-7V253452HR',
            'seo.analytics.gtm_id' => '',
        ]);

        $real = $this->get(route('contact'))->assertOk()->getContent();
        $this->assertStringContainsString('content="google-real-token-abc"', $real);
        $this->assertStringContainsString('content="bing-real-token-def"', $real);
        $this->assertStringContainsString('109876543210987', $real);
        $this->assertStringContainsString('C9A1B2C3D4E5F6', $real);
        $this->assertStringContainsString('G-7V253452HR', $real);
    }

    public function test_open_graph_titles_do_not_double_escape_ampersands(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $title = 'Urban Focus — IT Supplier South Africa | Networking, Laptops &amp; Security';

        $this->assertStringContainsString('<title>'.$title.'</title>', $html);
        $this->assertStringContainsString('property="og:title" content="'.$title.'"', $html);
        $this->assertStringContainsString('name="twitter:title" content="'.$title.'"', $html);
        $this->assertStringNotContainsString('&amp;amp;', $html);
    }

    public function test_visible_address_uses_rd_and_schema_keeps_the_street_separate(): void
    {
        config([
            'business.phone' => '087 550 1813',
            'business.phone_tel' => '0875501813',
            'business.address.line1' => '17 Waterloo RD',
            'business.address.line2' => 'Samrand Business Park',
            'business.address.postal_code' => '',
        ]);

        $html = $this->get(route('contact'))->assertOk()->getContent();

        $this->assertStringContainsString('17 Waterloo Rd', $html);
        $this->assertStringNotContainsString('17 Waterloo RD', $html);
        $this->assertStringContainsString('087 550 1813', $html);
        $this->assertStringContainsString('+27875501813', $html);
        $this->assertStringNotContainsString('+0875501813', $html);
        $this->assertStringContainsString('Samrand Business Park', $html);
        $this->assertStringNotContainsString('"postalCode"', $html);
        $this->assertStringContainsString('"streetAddress":"17 Waterloo Rd"', $html);
    }

    public function test_pages_sitemap_uses_the_configured_canonical_host(): void
    {
        config([
            'app.url' => 'https://www.urbanfocus.co.za',
            'seo.indexing.ping_search_engines' => false,
        ]);
        URL::forceRootUrl('https://mail.urbanfocus.co.za');
        URL::forceScheme('https');

        app(CategoryMapperService::class)->ensureCanonicalTree();
        app(SeoService::class)->clearCache();

        $pages = app(SeoService::class)->pagesSitemapXml();
        $index = app(SeoService::class)->sitemapXml();

        $this->assertStringContainsString('https://www.urbanfocus.co.za/', $pages);
        $this->assertStringContainsString('https://www.urbanfocus.co.za/shop', $pages);
        $this->assertStringContainsString('https://www.urbanfocus.co.za/sitemap-pages.xml', $index);
        $this->assertStringNotContainsString('https://mail.urbanfocus.co.za', $pages);
        $this->assertStringNotContainsString('https://mail.urbanfocus.co.za', $index);
        $this->assertDoesNotMatchRegularExpression('#<loc>https://urbanfocus\.co\.za/#', $pages);
    }

    public function test_self_referential_category_redirects_do_not_loop_and_leave_the_sitemap(): void
    {
        config(['seo.indexing.ping_search_engines' => false]);
        app(CategoryMapperService::class)->ensureCanonicalTree();

        $parent = Category::query()->where('slug', 'networking-connectivity')->whereNull('parent_id')->firstOrFail();
        $child = Category::query()->where('slug', 'network-cables')->where('parent_id', $parent->id)->firstOrFail();

        CategorySlugRedirect::create([
            'old_slug' => 'network-cables',
            'target_path' => 'networking-connectivity/network-cables',
        ]);
        CategorySlugRedirect::create([
            'old_slug' => 'networking-connectivity',
            'target_path' => 'networking-connectivity',
        ]);
        CategorySlugRedirect::create([
            'old_slug' => 'old-routers',
            'target_path' => 'networking-connectivity/routers',
        ]);
        CategorySlugRedirect::create([
            'old_slug' => 'missing-loop',
            'target_path' => 'missing-loop',
        ]);

        Category::create([
            'name' => 'Shoe Rack',
            'slug' => 'shoe-rack',
            'is_active' => true,
        ]);
        CategorySlugRedirect::create([
            'old_slug' => 'shoe-rack',
            'target_path' => 'shoe-rack',
        ]);

        $this->get('/category/networking-connectivity/network-cables')->assertOk();
        $this->get('/category/networking-connectivity')->assertOk();
        $this->get('/category/missing-loop')->assertNotFound();
        $this->get('/category/shoe-rack')->assertNotFound();
        $this->get('/category/old-routers')->assertRedirect('https://www.urbanfocus.co.za/category/networking-connectivity/routers');

        app(SeoService::class)->clearCache();
        $xml = app(SeoService::class)->pagesSitemapXml();
        $this->assertStringNotContainsString('/category/networking-connectivity/network-cables', $xml);
        $this->assertStringNotContainsString('/category/networking-connectivity<', $xml);
        $this->assertStringContainsString('/category/networking-connectivity/switches', $xml);

        $deleted = app(CategorySlugRedirectCleaner::class)->deleteSelfReferential();
        $this->assertGreaterThanOrEqual(3, $deleted);
        $this->assertDatabaseMissing('category_slug_redirects', ['old_slug' => 'network-cables']);
        $this->assertDatabaseMissing('category_slug_redirects', ['old_slug' => 'networking-connectivity']);
        $this->assertDatabaseMissing('category_slug_redirects', ['old_slug' => 'missing-loop']);
        $this->assertDatabaseHas('category_slug_redirects', [
            'old_slug' => 'old-routers',
            'target_path' => 'networking-connectivity/routers',
        ]);

        app(SeoService::class)->clearCache();
        $after = app(SeoService::class)->pagesSitemapXml();
        $this->assertStringContainsString('/category/networking-connectivity/network-cables', $after);
    }

    public function test_clean_slug_redirects_command_can_preview_without_deleting(): void
    {
        CategorySlugRedirect::create([
            'old_slug' => 'webcams',
            'target_path' => 'webcams',
        ]);

        $this->artisan('categories:clean-slug-redirects', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('category_slug_redirects', ['old_slug' => 'webcams']);

        $this->artisan('categories:clean-slug-redirects')->assertSuccessful();
        $this->assertDatabaseMissing('category_slug_redirects', ['old_slug' => 'webcams']);
    }

    public function test_production_redirects_non_canonical_hosts_but_not_admin_or_paystack(): void
    {
        config(['app.url' => 'https://www.urbanfocus.co.za']);
        $this->app['env'] = 'production';

        $shop = $this->get('http://urbanfocus.co.za/shop?page=2&q=poe');
        $shop->assertStatus(301);
        $location = (string) $shop->headers->get('Location');
        $this->assertStringStartsWith('https://www.urbanfocus.co.za/shop?', $location);
        $this->assertStringContainsString('page=2', $location);
        $this->assertStringContainsString('q=poe', $location);

        $mail = $this->get('http://mail.urbanfocus.co.za/contact');
        $mail->assertRedirect('https://www.urbanfocus.co.za/contact');

        $this->get('https://www.urbanfocus.co.za/contact')->assertOk();

        $admin = $this->get('http://mail.urbanfocus.co.za/admin');
        $this->assertNotSame(301, $admin->getStatusCode());
        $this->assertStringNotContainsString(
            'https://www.urbanfocus.co.za/admin',
            (string) $admin->headers->get('Location')
        );

        $webhook = $this->post('http://mail.urbanfocus.co.za/checkout/paystack/webhook', ['event' => 'charge.success']);
        $this->assertNotSame(301, $webhook->getStatusCode());

        $callback = $this->get('http://mail.urbanfocus.co.za/checkout/paystack/callback?reference=abc');
        $this->assertNotSame(301, $callback->getStatusCode());
    }

    public function test_local_environment_does_not_redirect_other_hosts(): void
    {
        config(['app.url' => 'https://www.urbanfocus.co.za']);
        $this->app['env'] = 'testing';

        $this->get('http://urbanfocus.co.za/contact')->assertOk();
    }

    public function test_urban_focus_brand_page_title_is_not_duplicated(): void
    {
        $brand = Brand::create([
            'name' => 'Urban Focus',
            'slug' => 'urban-focus',
            'is_active' => true,
        ]);

        $html = $this->get(route('brands.show', $brand))->assertOk()->getContent();

        $this->assertStringContainsString('<title>Urban Focus | IT Hardware Supplier South Africa</title>', $html);
        $this->assertStringNotContainsString('Urban Focus Supplier South Africa | Urban Focus', $html);
    }

    public function test_responses_do_not_advertise_x_powered_by(): void
    {
        $response = $this->get(route('contact'))->assertOk();
        $response->assertHeaderMissing('X-Powered-By');
    }
}
