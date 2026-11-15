<?php

namespace Tests\Unit;

use App\Models\Product;
use App\Services\SeoService;
use Illuminate\Pagination\LengthAwarePaginator;
use Tests\TestCase;

class SeoServiceTest extends TestCase
{
    public function test_collection_page_schema_uses_string_name_and_url(): void
    {
        $schema = app(SeoService::class)->collectionPageSchema(
            'Network Cables',
            'https://www.urbanfocus.co.za/category/networking-connectivity/network-cables',
            'Browse Network Cables at Urban Focus',
        );

        $json = json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
        $decoded = json_decode((string) $json, true);

        $this->assertIsArray($decoded);
        $this->assertSame('CollectionPage', $decoded['@type']);
        $this->assertSame('Network Cables', $decoded['name']);
        $this->assertIsString($decoded['name']);
        $this->assertSame(
            'https://www.urbanfocus.co.za/category/networking-connectivity/network-cables',
            $decoded['url']
        );
        $this->assertArrayNotHasKey('numberOfItems', $decoded);
        $this->assertArrayNotHasKey('mainEntity', $decoded);
    }

    public function test_collection_page_schema_puts_item_count_on_item_list(): void
    {
        $product = new Product(['name' => 'Cat6 Patch Cable', 'slug' => 'cat6-patch-cable']);
        $product->id = 1;

        $paginator = new LengthAwarePaginator(
            [$product],
            48,
            24,
            1,
            ['path' => '/category/networking-connectivity/network-cables']
        );

        $schema = app(SeoService::class)->collectionPageSchema(
            'Network Cables',
            'https://www.urbanfocus.co.za/category/networking-connectivity/network-cables',
            'Browse Network Cables at Urban Focus',
            $paginator,
        );

        $this->assertSame('ItemList', $schema['mainEntity']['@type']);
        $this->assertSame(1, $schema['mainEntity']['numberOfItems']);
        $this->assertSame(1, $schema['mainEntity']['itemListElement'][0]['position']);
        $this->assertIsString($schema['mainEntity']['itemListElement'][0]['url']);
        $this->assertArrayNotHasKey('numberOfItems', $schema);
    }

    public function test_robots_txt_repeats_disallows_for_named_crawlers(): void
    {
        $txt = app(SeoService::class)->robotsTxt();

        $this->assertStringContainsString('User-agent: *', $txt);
        $this->assertStringContainsString('User-agent: Googlebot', $txt);
        $this->assertStringContainsString('User-agent: Bingbot', $txt);
        $this->assertStringContainsString('User-agent: OAI-SearchBot', $txt);
        $this->assertGreaterThanOrEqual(2, substr_count($txt, 'Disallow: /admin'));
        $this->assertStringContainsString('Sitemap: ', $txt);
    }

    public function test_organization_phone_is_e164_and_street_excludes_the_business_park(): void
    {
        config([
            'business.phone_tel' => '0875501813',
            'business.address.line1' => '17 Waterloo RD',
            'business.address.line2' => 'Samrand Business Park',
            'business.address.city' => 'Centurion',
            'business.address.province' => 'Gauteng',
            'business.address.postal_code' => '',
        ]);

        $schema = app(SeoService::class)->organizationSchema();

        $this->assertSame('+27875501813', $schema['telephone']);
        $this->assertSame('+27875501813', $schema['contactPoint']['telephone']);
        $this->assertSame('17 Waterloo Rd', $schema['address']['streetAddress']);
        $this->assertSame('Samrand Business Park', $schema['address']['name']);
        $this->assertSame('Centurion', $schema['address']['addressLocality']);
        $this->assertArrayNotHasKey('postalCode', $schema['address']);
        $this->assertArrayNotHasKey('aggregateRating', $schema);
        $this->assertArrayNotHasKey('review', $schema);

        config(['business.address.postal_code' => '0157']);
        $withPostal = app(SeoService::class)->organizationSchema();
        $this->assertSame('0157', $withPostal['address']['postalCode']);
    }

    public function test_sitemap_locs_use_the_app_url_host_not_a_foreign_or_mail_host(): void
    {
        config(['app.url' => 'https://www.urbanfocus.co.za']);
        $seo = app(SeoService::class);
        $method = new \ReflectionMethod($seo, 'canonicalLoc');

        $this->assertSame(
            'https://www.urbanfocus.co.za/sitemap-pages.xml',
            $method->invoke($seo, 'https://mail.urbanfocus.co.za/sitemap-pages.xml')
        );
        $this->assertSame(
            'https://www.urbanfocus.co.za/category/webcams',
            $method->invoke($seo, 'https://urbanfocus.co.za/category/webcams')
        );
        $this->assertSame(
            'https://cdn.example.com/photo.jpg',
            $method->invoke($seo, 'https://cdn.example.com/photo.jpg')
        );
    }

    public function test_placeholder_tracking_tokens_are_treated_as_unset(): void
    {
        $this->assertNull(seo_public_token(''));
        $this->assertNull(seo_public_token(' your-code '));
        $this->assertNull(seo_public_token('123456789'));
        $this->assertNull(seo_public_token('XXXXXXXX'));
        $this->assertNull(seo_public_token('G-XXXXXXXXXX'));
        $this->assertSame('G-7V253452HR', seo_public_token('G-7V253452HR'));
        $this->assertSame('google-real-token', seo_public_token('google-real-token'));
    }

    public function test_national_phone_numbers_including_a_bogus_plus_zero_become_e164(): void
    {
        $this->assertSame('+27875501813', business_phone_e164('0875501813'));
        $this->assertSame('+27875501813', business_phone_e164('087 550 1813'));
        $this->assertSame('+27875501813', business_phone_e164('+0875501813'));
        $this->assertSame('+27875501813', business_phone_e164('+27875501813'));
    }

    public function test_llms_txt_names_urban_focus_and_the_south_african_market(): void
    {
        $txt = app(SeoService::class)->llmsTxt();

        $this->assertStringContainsString('# Urban Focus', $txt);
        $this->assertStringContainsString('South Africa', $txt);
        $this->assertStringContainsString('/sitemap.xml', $txt);
    }
}
