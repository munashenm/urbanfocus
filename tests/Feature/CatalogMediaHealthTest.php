<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Services\CatalogMediaHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogMediaHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_does_not_modify_products_and_counts_problems(): void
    {
        $valid = Product::factory()->create([
            'name' => 'UniFi Switch Lite 8 PoE',
            'sku' => 'USW-Lite-8-PoE',
            'brand' => 'Ubiquiti',
            'price' => 2499,
            'slug' => 'unifi-switch-lite-8-poe',
        ]);
        $this->attachJpeg($valid, 400, 400);

        $missing = Product::factory()->create([
            'name' => 'Huawei AR6120',
            'sku' => '02356UUM',
            'brand' => 'Huawei',
            'price' => 8999,
            'slug' => 'huawei-ar6120',
            'meta_title' => 'Buy networking hardware online',
            'short_description' => '',
            'description' => '',
        ]);

        $broken = Product::factory()->create([
            'name' => 'Missing File Camera',
            'sku' => 'CAM-404',
            'brand' => 'Dahua',
            'price' => 1200,
            'slug' => 'missing-file-camera',
        ]);
        ProductImage::create([
            'product_id' => $broken->id,
            'path' => 'products/'.$broken->id.'/does-not-exist.jpg',
            'alt_text' => 'broken',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $placeholder = Product::factory()->create([
            'name' => 'Placeholder Router',
            'sku' => 'RTR-PH',
            'brand' => 'TP-Link',
            'price' => 800,
            'slug' => 'placeholder-router',
        ]);
        ProductImage::create([
            'product_id' => $placeholder->id,
            'path' => 'images/product-placeholder.svg',
            'alt_text' => 'coming soon',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        Product::factory()->create([
            'name' => 'Duplicate SKU A',
            'sku' => 'DUP-1',
            'brand' => 'Dell',
            'price' => 100,
            'slug' => 'duplicate-sku-a',
        ]);
        Product::factory()->create([
            'name' => 'Duplicate SKU B',
            'sku' => 'DUP-1',
            'brand' => 'Dell',
            'price' => 100,
            'slug' => 'duplicate-sku-b',
        ]);

        $report = app(CatalogMediaHealthService::class)->run(checkRemote: false, recover: false);

        $this->assertSame(6, $report['counts']['total_scanned']);
        $this->assertGreaterThanOrEqual(1, $report['counts']['valid_images']);
        $this->assertGreaterThanOrEqual(1, $report['counts']['missing_images']);
        $this->assertGreaterThanOrEqual(1, $report['counts']['broken_images']);
        $this->assertGreaterThanOrEqual(1, $report['counts']['placeholder_images']);
        $this->assertSame(0, $report['counts']['recovered_images']);
        $this->assertSame(1, $report['counts']['duplicate_skus']);
        $this->assertNotEmpty($report['huawei_02356uum']);
        $this->assertSame('02356UUM', $report['huawei_02356uum'][0]['sku']);
        $this->assertContains('meta title does not contain product name', $report['huawei_02356uum'][0]['problems']);

        $missing->refresh();
        $this->assertFalse($missing->images()->exists());

        $this->assertFileExists(storage_path('app/reports/missing-product-images.csv'));
        $this->assertFileExists(storage_path('app/reports/catalogue-data-problems.csv'));
        $csv = (string) file_get_contents(storage_path('app/reports/missing-product-images.csv'));
        $this->assertStringContainsString('02356UUM', $csv);
    }

    public function test_recovery_copies_exact_sku_and_brand_sibling_image_only(): void
    {
        $source = Product::factory()->create([
            'name' => 'Dell Latitude 5540',
            'sku' => 'LAT-5540',
            'brand' => 'Dell',
            'price' => 18000,
            'slug' => 'dell-latitude-5540',
        ]);
        $this->attachJpeg($source, 400, 400);

        $sameSku = Product::factory()->create([
            'name' => 'Dell Latitude 5540',
            'sku' => 'LAT-5540',
            'brand' => 'Dell',
            'price' => 18000,
            'slug' => 'dell-latitude-5540-dup',
        ]);

        $unrelated = Product::factory()->create([
            'name' => 'Dell Latitude 5540',
            'sku' => 'LAT-5550',
            'brand' => 'Dell',
            'price' => 19000,
            'slug' => 'dell-latitude-5550',
        ]);

        $report = app(CatalogMediaHealthService::class)->run(checkRemote: false, recover: true);

        $this->assertTrue($sameSku->fresh()->images()->exists());
        $this->assertFalse($unrelated->fresh()->images()->exists());
        $this->assertGreaterThanOrEqual(1, $report['counts']['recovered_images']);
        $this->assertContains('sibling_sku_brand_image', array_column($report['recovered'], 'source'));
    }

    public function test_placeholder_image_is_not_used_in_merchant_or_schema(): void
    {
        $product = Product::factory()->create([
            'name' => 'AR6120 Router',
            'sku' => '02356UUM',
            'brand' => 'Huawei',
            'price' => 8999,
            'slug' => 'huawei-ar6120-schema',
        ]);
        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'images/product-placeholder.svg',
            'is_primary' => true,
            'sort_order' => 0,
        ]);
        $product->load('images');

        $this->assertNull($product->primary_image_url);
        $this->assertContains('no_image', $product->googleMerchantIssues());
        $this->assertSame('Huawei AR6120 Router', $product->imageAlt());

        $schema = $product->toSchemaArray();
        $this->assertArrayNotHasKey('image', $schema);
    }

    public function test_image_alt_is_brand_and_product_name_without_keyword_stuffing(): void
    {
        $product = Product::factory()->create([
            'name' => 'Ubiquiti UniFi Switch Ultra',
            'brand' => 'Ubiquiti',
        ]);

        $this->assertSame('Ubiquiti UniFi Switch Ultra', $product->imageAlt());
        $this->assertStringNotContainsString('South Africa', $product->imageAlt());
    }

    protected function attachJpeg(Product $product, int $width, int $height): void
    {
        $this->assertTrue(function_exists('imagecreatetruecolor'), 'GD is required for catalogue media tests.');

        $image = imagecreatetruecolor($width, $height);
        $fill = imagecolorallocate($image, 230, 235, 240);
        imagefill($image, 0, 0, $fill);
        ob_start();
        imagejpeg($image, null, 80);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $path = 'products/'.$product->id.'/photo.jpg';
        Storage::disk('public')->put($path, $bytes);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => $path,
            'alt_text' => $product->imageAlt(),
            'sort_order' => 0,
            'is_primary' => true,
        ]);
        $product->load('images');
    }
}
