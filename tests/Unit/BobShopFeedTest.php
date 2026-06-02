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

        $this->assertStringContainsString('<code>AP-TEST-01</code>', $xml);
        $this->assertStringContainsString('<availableQuantity>5</availableQuantity>', $xml);
        $this->assertStringContainsString('<category>Networking</category>', $xml);
        $this->assertStringContainsString('<name>Test Access Point</name>', $xml);
        $this->assertStringContainsString('<price>1999.00</price>', $xml);
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

        $this->assertStringNotContainsString('<code>OOS-PRINT</code>', $xml);
    }
}
