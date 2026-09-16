<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\HighMarginCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HighMarginCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'social-posting.enabled' => false,
            'catalog.high_margin_path' => base_path('tests/fixtures/high-margin-sample.php'),
        ]);

        $this->prepareFixtureImages();
    }

    public function test_creates_products_at_exact_vat_price_without_specialist_topup(): void
    {
        $catalog = app(HighMarginCatalogService::class);
        $result = $catalog->sync();

        $this->assertSame(2, $result['created']);
        $this->assertSame(0, $result['errors']);

        $tester = Product::where('sku', 'UF-TEST-HM-A')->first();
        $this->assertNotNull($tester);
        $this->assertSame(2749.00, (float) $tester->price);
        $this->assertSame('NOYAFA', $tester->brand);
        $this->assertSame('TEST-A', $tester->model_number);
        $this->assertSame('available_on_order', $tester->availabilityKey());
        $this->assertSame('AVAILABLE ON ORDER', $tester->availabilityLabel());
        $this->assertSame('backorder', $tester->googleFeedAvailability());
        $this->assertSame('Ships on confirmed supplier availability', $tester->deliveryEstimate());
        $this->assertSame(
            HighMarginCatalogService::CATALOG_RANGE_SPEC_VALUE,
            $tester->specifications[HighMarginCatalogService::CATALOG_RANGE_SPEC_KEY] ?? null
        );
        $this->assertGreaterThanOrEqual(4, $tester->images()->count());
        $this->assertSame('Test network tester on a white background', (string) $tester->images()->orderBy('sort_order')->first()?->alt_text);
        $this->assertArrayNotHasKey('Related SKUs', $tester->specificationsList());
        $this->assertArrayNotHasKey('ICASA note', $tester->specificationsList());
        $this->assertStringContainsString('ICASA', (string) ($tester->specifications['ICASA note'] ?? ''));
        $this->assertStringNotContainsString('ICASA', (string) $tester->storefrontDescriptionHtml());
        $this->assertStringNotContainsString('Google Shopping', (string) $tester->description);
        $this->assertStringNotContainsString('structured data', (string) $tester->description);
        $this->assertStringContainsString('<h3>Key features</h3>', (string) $tester->description);
        $this->assertStringContainsString('incl. VAT', 'R '.number_format((float) $tester->price, 2).' incl. VAT');

        $schema = $tester->toSchemaArray();
        $this->assertSame('Product', $schema['@type']);
        $this->assertSame('UF-TEST-HM-A', $schema['sku']);
        $this->assertSame('TEST-A', $schema['mpn']);
        $this->assertSame('2749.00', $schema['offers']['price']);
        $this->assertSame('ZAR', $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/BackOrder', $schema['offers']['availability']);
        $this->assertSame('https://schema.org/NewCondition', $schema['offers']['itemCondition']);
        $this->assertArrayNotHasKey('gtin13', $schema);
        $this->assertArrayNotHasKey('gtin8', $schema);
        $this->assertArrayNotHasKey('gtin12', $schema);
        $this->assertArrayNotHasKey('gtin14', $schema);
    }

    public function test_hides_internal_flags_and_links_related_skus(): void
    {
        app(HighMarginCatalogService::class)->sync();

        $otdr = Product::where('sku', 'UF-TEST-HM-B')->firstOrFail();
        $this->assertSame(8799.00, (float) $otdr->price);
        $this->assertSame(['UF-TEST-HM-A'], $otdr->relatedSkuList());
        $this->assertArrayNotHasKey('Supplier image approval', $otdr->specificationsList());
        $this->assertSame('Supplier image approval required.', $otdr->specifications['Supplier image approval'] ?? null);

        $response = $this->get(route('products.show', $otdr));
        $response->assertOk();
        $response->assertSee('R '.number_format(8799, 2), false);
        $response->assertSee('incl. VAT', false);
        $response->assertSee('AVAILABLE ON ORDER', false);
        $response->assertDontSee('ICASA');
        $response->assertDontSee('Google Shopping');
        $response->assertDontSee('structured data');
        $response->assertDontSee('Supplier image approval');
        $response->assertSee('Test High-Margin Network Tester', false);
    }

    public function test_dry_run_and_exact_sku_update(): void
    {
        $preview = app(HighMarginCatalogService::class)->sync(dryRun: true);
        $this->assertSame(2, $preview['created']);
        $this->assertSame(0, Product::count());

        $this->artisan('catalog:sync-high-margin', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('would be created');

        app(HighMarginCatalogService::class)->sync();
        $product = Product::where('sku', 'UF-TEST-HM-A')->firstOrFail();
        $product->update(['price' => 1999.00, 'description' => 'stale']);

        $result = app(HighMarginCatalogService::class)->sync();
        $this->assertSame(0, $result['created']);
        $this->assertGreaterThanOrEqual(1, $result['updated']);
        $this->assertSame(2749.00, (float) $product->fresh()->price);
        $this->assertStringContainsString('Key features', (string) $product->fresh()->description);
    }

    protected function prepareFixtureImages(): void
    {
        $source = base_path('public/images/high-margin/UF-NOY-NF8209PRO');
        $files = glob($source.DIRECTORY_SEPARATOR.'*.jpg') ?: [];
        $this->assertNotEmpty($files, 'NOYAFA listing photos are required as fixture image sources.');

        $root = storage_path('framework/testing/high-margin-images');
        $sample = require base_path('tests/fixtures/high-margin-sample.php');
        foreach ($sample as $i => $item) {
            $dir = $root.DIRECTORY_SEPARATOR.$item['sku'];
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            foreach (array_values($files) as $index => $file) {
                copy($file, $dir.DIRECTORY_SEPARATOR.sprintf('%02d.jpg', $index + 1));
            }
            $sample[$i]['image_dir'] = $dir;
        }

        $path = storage_path('framework/testing/high-margin-sample.php');
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }
        file_put_contents($path, '<?php return '.var_export($sample, true).';');
        config(['catalog.high_margin_path' => $path]);
    }
}
