<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BobShopFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_bob_shop_xml_contains_required_product_fields(): void
    {
        Cache::flush();

        $category = Category::create(['name' => 'Networking', 'slug' => 'networking', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Test Access Point',
            'slug' => 'test-access-point',
            'sku' => 'AP-TEST-01',
            'short_description' => 'Dual-band WiFi access point for small business deployments.',
            'price' => 1999,
            'stock_quantity' => 5,
            'manage_stock' => true,
            'in_stock' => true,
            'brand' => 'Ubiquiti',
            'is_active' => true,
        ]);

        ProductImage::create([
            'product_id' => $product->id,
            'path' => 'products/'.$product->id.'/test.jpg',
            'sort_order' => 0,
            'is_primary' => true,
        ]);

        $xml = app(FeedService::class)->bobShopXml();

        $this->assertStringContainsString('<ROOT>', $xml);
        $this->assertStringContainsString('<Products>', $xml);
        $this->assertStringContainsString('<Product>', $xml);
        $this->assertStringContainsString('<ProductCode><![CDATA[AP-TEST-01]]></ProductCode>', $xml);
        $this->assertStringContainsString('<AvailableQty>5</AvailableQty>', $xml);
        $this->assertStringContainsString('<Category><![CDATA[Networking]]></Category>', $xml);
        $this->assertStringContainsString('<ProductName><![CDATA[Test Access Point]]></ProductName>', $xml);
        $this->assertStringContainsString('<Price>1999.00</Price>', $xml);
        $this->assertStringContainsString('<Brand>Ubiquiti</Brand>', $xml);
    }

    public function test_out_of_stock_product_excluded_from_bob_shop_feed(): void
    {
        Cache::flush();

        $category = Category::create(['name' => 'Printers', 'slug' => 'printers', 'is_active' => true]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Out of Stock Printer',
            'slug' => 'out-of-stock-printer',
            'sku' => 'OOS-PRINT',
            'short_description' => 'Printer with no stock for Bob Shop feed test.',
            'price' => 500,
            'stock_quantity' => 0,
            'manage_stock' => true,
            'in_stock' => false,
            'is_active' => true,
        ]);

        $xml = app(FeedService::class)->bobShopXml();

        $this->assertStringNotContainsString('<ProductCode><![CDATA[OOS-PRINT]]></ProductCode>', $xml);
    }

    public function test_bob_shop_bulkload_csv_matches_official_template(): void
    {
        Cache::flush();

        $category = Category::create(['name' => 'Networking', 'slug' => 'networking', 'is_active' => true]);
        Product::create([
            'category_id' => $category->id,
            'name' => 'Bulkload Test Router',
            'slug' => 'bulkload-test-router',
            'sku' => 'BB-ROUTER-1',
            'short_description' => 'Gigabit router for Bob Shop bulk CSV export testing.',
            'price' => 899,
            'stock_quantity' => 3,
            'manage_stock' => true,
            'in_stock' => true,
            'is_active' => true,
        ]);

        $csv = app(FeedService::class)->bobShopBulkloadCsv();
        $lines = preg_split('/\r\n|\r|\n/', trim($csv));

        $this->assertGreaterThanOrEqual(2, count($lines));
        $this->assertStringContainsString('Listing Type [mandatory]', $lines[0]);
        $this->assertStringContainsString('FIXED_PRICE', $lines[1]);
        $this->assertStringContainsString('BB-ROUTER-1', $lines[1]);
        $this->assertStringContainsString('899.00', $lines[1]);
        $this->assertStringEndsWith(',End', $lines[1]);
    }
}
