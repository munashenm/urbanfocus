<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\InternalPricingCopySanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalPricingCopyScrubTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_page_hides_internal_pricing_copy_and_shows_why_buy_block(): void
    {
        $product = Product::factory()->create([
            'name' => 'Ubiquiti UniFi Enterprise Wi-Fi 7 Access Point',
            'slug' => 'ubiquiti-unifi-enterprise-wifi-7-access-point',
            'brand' => 'Ubiquiti',
            'sku' => 'U7-ENTERPRISE',
            'short_description' => 'Wi-Fi 7 AP. Prices on this page already include a buffer for Paystack card fees.',
            'description' => '<p>Ceiling-mount Wi-Fi 7 access point for enterprise campuses.</p><h3>Why buy from Urban Focus</h3><p>Prices on this page already include a buffer for Paystack card fees and a catalogue markup so we do not undercut our price.</p>',
            'meta_description' => 'U7 Enterprise with Paystack fee buffer and our cost markup.',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertDontSee('Paystack card fees', false)
            ->assertDontSee('catalogue markup', false)
            ->assertDontSee('undercut our price', false)
            ->assertDontSee('our cost', false)
            ->assertSee('Why buy from Urban Focus', false)
            ->assertSee('VAT-compliant invoicing', false)
            ->assertSee('Ceiling-mount Wi-Fi 7 access point', false)
            ->assertSee('nationwide delivery', false);
    }

    public function test_google_feed_description_does_not_include_fee_language(): void
    {
        $product = Product::factory()->create([
            'short_description' => 'Enterprise Wi-Fi 7 AP. Includes a buffer for Paystack card fees.',
            'description' => '<p>Ceiling-mount access point.</p>',
        ]);

        $this->assertStringNotContainsString('Paystack', $product->googleFeedDescription());
        $this->assertStringNotContainsString('card fee', $product->googleFeedDescription());
        $this->assertStringContainsString('Enterprise Wi-Fi 7 AP', $product->googleFeedDescription());
    }

    public function test_scrub_command_rewrites_stored_copy(): void
    {
        $product = Product::factory()->create([
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
    }

    public function test_sanitizer_reports_leaks_across_the_catalog(): void
    {
        Product::factory()->create([
            'short_description' => 'Clean enterprise access point.',
        ]);
        Product::factory()->create([
            'short_description' => 'Priced to cover card fees against FirstShop.',
        ]);

        $stats = app(InternalPricingCopySanitizer::class)->scrubCatalog(dryRun: true);

        $this->assertSame(2, $stats['processed']);
        $this->assertSame(1, $stats['updated']);
    }
}
