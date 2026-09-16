<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use App\Services\InternalPricingCopySanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalPricingCopyScrubTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Product::$sanitizeCustomerCopyOnSave = true;
        parent::tearDown();
    }

    protected function createDirtyProduct(array $attributes): Product
    {
        Product::$sanitizeCustomerCopyOnSave = false;
        $product = Product::factory()->create($attributes);
        Product::$sanitizeCustomerCopyOnSave = true;

        return $product;
    }

    public function test_product_page_hides_internal_pricing_copy_and_shows_why_buy_block(): void
    {
        $product = $this->createDirtyProduct([
            'name' => 'Ubiquiti UniFi Enterprise Wi-Fi 7 Access Point',
            'slug' => 'ubiquiti-unifi-enterprise-wifi-7-access-point',
            'brand' => 'Ubiquiti',
            'sku' => 'U7-ENTERPRISE',
            'short_description' => 'Wi-Fi 7 AP. Prices on this page already include a buffer for Paystack card fees.',
            'description' => '<p>The Ubiquiti UniFi Enterprise Wi-Fi 7 Access Point is an enterprise wireless access point supplied by Urban Focus for South African businesses, integrators and public-sector buyers. Pricing is set to win against local distributors without giving the product away.</p><h3>Why buy from Urban Focus</h3><p>Prices on this page already include a buffer for Paystack card fees and a catalogue markup so we do not undercut our price.</p>',
            'meta_description' => 'U7 Enterprise with Paystack fee buffer and our cost markup.',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Paystack card fees', false)
            ->assertDontSee('catalogue markup', false)
            ->assertDontSee('undercut our price', false)
            ->assertDontSee('our cost', false)
            ->assertDontSee('Pricing is set to win', false)
            ->assertDontSee('giving the product away', false)
            ->assertSee('Why buy from Urban Focus', false)
            ->assertSee('VAT-compliant invoicing', false)
            ->assertSee('enterprise wireless access point supplied by Urban Focus', false)
            ->assertSee('nationwide delivery', false);
    }

    public function test_google_feed_and_schema_description_do_not_include_fee_language(): void
    {
        $product = $this->createDirtyProduct([
            'short_description' => 'Enterprise Wi-Fi 7 AP. Includes a buffer for Paystack card fees.',
            'description' => '<p>Ceiling-mount access point.</p>',
        ]);

        $this->assertStringNotContainsString('Paystack', $product->googleFeedDescription());
        $this->assertStringNotContainsString('card fee', $product->googleFeedDescription());
        $this->assertStringContainsString('Enterprise Wi-Fi 7 AP', $product->googleFeedDescription());
        $this->assertStringNotContainsString('Paystack', $product->toSchemaArray()['description']);
    }

    public function test_public_api_sanitises_legacy_dirty_descriptions(): void
    {
        $product = $this->createDirtyProduct([
            'sku' => 'U7-ENTERPRISE',
            'slug' => 'ubiquiti-unifi-enterprise-wi-fi-7-access-point',
            'is_active' => true,
            'short_description' => 'Wi-Fi 7 AP. Pricing is set to win against local distributors.',
            'description' => '<p>Enterprise AP for offices. Card/EFT charges are built into the prices.</p>',
            'meta_description' => 'Paystack fee buffer and sustainable margin.',
        ]);

        Setting::set('api_key', 'test-api-key', 'api');

        $this->getJson('/api/products/'.$product->slug, ['X-API-Key' => 'test-api-key'])
            ->assertOk()
            ->assertJsonMissing(['short_description' => $product->getRawOriginal('short_description')])
            ->assertJsonPath('data.short_description', 'Wi-Fi 7 AP.')
            ->assertJsonFragment(['description' => 'Enterprise AP for offices.'])
            ->assertDontSee('Paystack', false)
            ->assertDontSee('sustainable margin', false)
            ->assertDontSee('Card/EFT', false);
    }

    public function test_saving_a_product_strips_internal_pricing_copy(): void
    {
        $product = Product::factory()->create([
            'sku' => 'U7-ENTERPRISE',
            'short_description' => 'Wi-Fi 7 AP. Pricing is set to win against local distributors without giving the product away.',
            'description' => '<p>Enterprise AP for schools. Catalogue top-up protects sustainable margin.</p>',
        ]);

        $fresh = $product->fresh();
        $this->assertSame('Wi-Fi 7 AP.', $fresh->short_description);
        $this->assertStringContainsString('Enterprise AP for schools.', (string) $fresh->description);
        $this->assertStringNotContainsString('Pricing is set to win', (string) $fresh->description);
        $this->assertStringNotContainsString('sustainable margin', (string) $fresh->description);
        $this->assertFalse(app(InternalPricingCopySanitizer::class)->needsScrub($fresh));
    }

    public function test_scrub_command_rewrites_stored_copy(): void
    {
        $product = $this->createDirtyProduct([
            'sku' => 'U7-ENTERPRISE',
            'name' => 'Ubiquiti U7 Enterprise',
            'short_description' => 'Wi-Fi 7 AP. Prices on this page already include a buffer for Paystack card fees.',
            'description' => '<p>Prices on this page already include a buffer for Paystack card fees.</p><p>Ceiling-mount Wi-Fi 7 access point.</p>',
            'meta_description' => 'Includes Paystack fee buffer and supplier price markup.',
            'specifications' => [
                'Ports' => '2.5GbE',
                'FAQ 1 question' => 'How do you price this?',
                'FAQ 1 answer' => 'We add a catalogue top-up so we do not undercut our cost.',
            ],
        ]);

        $this->artisan('catalog:scrub-internal-copy --dry-run')
            ->assertSuccessful();

        $this->assertStringContainsString('Paystack', (string) $product->fresh()->description);

        $this->artisan('catalog:scrub-internal-copy')
            ->assertSuccessful();

        $fresh = $product->fresh();
        $this->assertStringNotContainsString('Paystack', (string) $fresh->description);
        $this->assertStringNotContainsString('supplier price', (string) $fresh->meta_description);
        $this->assertStringContainsString('Ceiling-mount Wi-Fi 7 access point', (string) $fresh->description);
        $this->assertSame('Wi-Fi 7 AP.', $fresh->short_description);
        $this->assertSame([], $fresh->listingFaqs());
        $this->assertSame([], app(InternalPricingCopySanitizer::class)->auditCatalog());
    }

    public function test_sanitizer_reports_leaks_across_the_catalog(): void
    {
        Product::factory()->create([
            'short_description' => 'Clean enterprise access point.',
        ]);
        $this->createDirtyProduct([
            'short_description' => 'Priced to cover card fees against FirstShop.',
        ]);

        $stats = app(InternalPricingCopySanitizer::class)->scrubCatalog(dryRun: true);

        $this->assertSame(2, $stats['processed']);
        $this->assertSame(1, $stats['updated']);
    }

    public function test_product_page_hides_seo_implementation_commentary_and_keeps_schema(): void
    {
        $product = $this->createDirtyProduct([
            'name' => 'Nitrokey 3C NFC',
            'slug' => 'nitrokey-3c-nfc',
            'brand' => 'Nitrokey',
            'sku' => 'UF-NK-3C-NFC',
            'model_number' => 'NK-3C-NFC',
            'price' => 1299,
            'short_description' => 'USB-C plus NFC FIDO2 key for South African Microsoft 365 and Google Workspace MFA.',
            'description' => '<p>The Nitrokey 3C NFC is a FIDO2 hardware security key supplied by Urban Focus. Listings are prepared for Google Shopping, Google Images and organic search.</p><h4>Is this listing ready for Google Shopping?</h4><p>Yes. Each specialist product includes a unique title, description, MPN/SKU, brand, image alt text and structured data so Google Merchant Center, Google Images and AI search overviews can index it.</p>',
            'specifications' => [
                'Interface' => 'USB-C + NFC',
                'FAQ 1 question' => 'Can I buy the Nitrokey 3C NFC in South Africa?',
                'FAQ 1 answer' => 'Yes. Urban Focus supplies it with a VAT invoice nationwide.',
                'FAQ 2 question' => 'Is this listing ready for Google Shopping?',
                'FAQ 2 answer' => 'Yes. Each specialist product includes a unique title, description, MPN/SKU, brand, image alt text and structured data so Google Merchant Center, Google Images and AI search overviews can index it.',
            ],
        ]);

        $html = $this->get(route('products.show', $product))->assertOk()->getContent();

        $this->assertStringContainsString('Nitrokey 3C NFC', $html);
        $this->assertStringContainsString('Google Workspace MFA', $html);
        $this->assertStringContainsString('SKU: <strong>UF-NK-3C-NFC</strong>', $html);
        $this->assertStringContainsString('MPN: <strong>NK-3C-NFC</strong>', $html);
        $this->assertStringContainsString('Brand: <strong>Nitrokey</strong>', $html);
        $this->assertStringContainsString('Technical specifications', $html);
        $this->assertStringContainsString('Ideal applications', $html);
        $this->assertStringContainsString('application/ld+json', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"Product"/', $html);
        $this->assertMatchesRegularExpression('/"@type":\s*"Offer"/', $html);
        $this->assertMatchesRegularExpression('/"sku":\s*"UF-NK-3C-NFC"/', $html);
        $this->assertMatchesRegularExpression('/"mpn":\s*"NK-3C-NFC"/', $html);
        $this->assertStringNotContainsString('Is this listing ready for Google Shopping?', $html);
        $this->assertStringNotContainsString('Google Merchant Center', $html);
        $this->assertStringNotContainsString('structured data', $html);
        $this->assertStringNotContainsString('image alt text', $html);
        $this->assertStringNotContainsString('AI search overviews', $html);
        $this->assertStringNotContainsString('Listings are prepared for Google Shopping', $html);

        $faqs = $product->listingFaqs();
        $this->assertCount(1, $faqs);
        $this->assertSame('Can I buy the Nitrokey 3C NFC in South Africa?', $faqs[0]['question']);
        $this->assertStringNotContainsString('Google Shopping', json_encode($product->faqSchemaArray()));
    }
}
