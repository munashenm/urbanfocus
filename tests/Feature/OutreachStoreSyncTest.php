<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachStoreSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set('api_key', 'test-api-key', 'api');
    }

    public function test_store_health_accepts_the_bearer_key(): void
    {
        $this->getJson('/api/store/health')->assertUnauthorized();

        $this->getJson('/api/store/health', ['Authorization' => 'Bearer test-api-key'])
            ->assertOk()
            ->assertJson(['status' => 'ok']);
    }

    public function test_outreach_can_match_update_and_create_catalogue_products(): void
    {
        $product = Product::factory()->create([
            'sku' => 'SW-24',
            'name' => 'Old switch',
            'price' => 100,
            'sale_price' => 80,
            'stock_quantity' => 9,
            'specifications' => ['Ports' => '24'],
        ]);

        $headers = ['Authorization' => 'Bearer test-api-key'];

        $this->getJson('/api/store/products?sku=sw-24', $headers)
            ->assertOk()
            ->assertJson(['sku' => 'SW-24']);

        $this->patchJson('/api/store/products/SW-24/stock', [
            'quantity' => 4,
            'stockStatus' => 'in_stock',
        ], $headers)->assertOk();

        $this->patchJson('/api/store/products/SW-24/price', [
            'unitPriceCents' => 250000,
            'currency' => 'ZAR',
        ], $headers)->assertOk();

        $this->patchJson('/api/store/products/SW-24/content', [
            'name' => '24-port switch',
            'description' => 'Gigabit switch',
            'specifications' => '24 gigabit ports',
        ], $headers)->assertOk();

        $this->patchJson('/api/store/products/SW-24/images', [
            'imageUrls' => ['https://cdn.example.com/switch.jpg'],
        ], $headers)->assertOk();

        $product->refresh();
        $this->assertSame(4, $product->stock_quantity);
        $this->assertSame('2500.00', $product->price);
        $this->assertNull($product->sale_price);
        $this->assertSame('24-port switch', $product->name);
        $this->assertSame('24', $product->specifications['Ports']);
        $this->assertSame('24 gigabit ports', $product->specifications['Details']);
        $this->assertSame('https://cdn.example.com/switch.jpg', $product->images()->first()->path);

        $this->getJson('/api/store/products?sku=NEW-1', $headers)->assertNotFound();

        $this->postJson('/api/store/products', [
            'sku' => 'NEW-1',
            'name' => 'New cable',
            'description' => 'Cat6',
            'specifications' => '',
            'unitPriceCents' => 9900,
            'currency' => 'ZAR',
            'stockQuantity' => 2,
            'published' => true,
            'imageUrls' => [],
        ], $headers)->assertCreated();

        $this->assertDatabaseHas('products', ['sku' => 'NEW-1', 'price' => 99]);
    }

    public function test_orders_use_the_outreach_status_names(): void
    {
        $order = Order::create([
            'order_number' => 'UF-1001',
            'status' => 'pending_payment',
            'payment_status' => 'pending',
            'subtotal' => 100,
            'total' => 115,
            'currency' => 'ZAR',
            'customer_email' => 'buyer@example.com',
            'billing_first_name' => 'Jane',
            'billing_last_name' => 'Smith',
            'billing_company' => 'Acme',
            'billing_address_line_1' => '1 Main',
            'billing_city' => 'Johannesburg',
            'billing_province' => 'Gauteng',
            'billing_postal_code' => '2000',
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_name' => 'Switch',
            'product_sku' => 'SW-24',
            'unit_price' => 115,
            'quantity' => 1,
            'line_total' => 115,
        ]);

        $this->getJson('/api/store/orders?limit=10', ['Authorization' => 'Bearer test-api-key'])
            ->assertOk()
            ->assertJsonPath('orders.0.status', 'pending')
            ->assertJsonPath('orders.0.email', 'buyer@example.com')
            ->assertJsonPath('orders.0.totalCents', 11500)
            ->assertJsonPath('orders.0.lines.0.sku', 'SW-24');
    }

    public function test_catalogue_read_lists_every_product_without_changing_it(): void
    {
        $active = Product::factory()->create([
            'sku' => 'SW-24',
            'model_number' => 'D11G8ET',
            'barcode' => '6001234567890',
            'brand' => 'HP',
            'price' => 100,
            'sale_price' => 90,
            'stock_quantity' => 0,
            'in_stock' => false,
            'name' => 'HP Laptop',
        ]);
        Product::factory()->create([
            'sku' => null,
            'is_active' => false,
            'price' => 0,
            'name' => 'Unpublished item',
        ]);
        $stamp = $active->updated_at;
        $headers = ['Authorization' => 'Bearer test-api-key'];

        $first = $this->getJson('/api/store/catalogue?per_page=1&page=1', $headers)->assertOk();
        $first->assertJsonPath('total', 2);
        $first->assertJsonPath('lastPage', 2);
        $first->assertJsonPath('products.0.storeProductId', (string) $active->id);
        $first->assertJsonPath('products.0.sku', 'SW-24');
        $first->assertJsonPath('products.0.manufacturerPartNumber', 'D11G8ET');
        $first->assertJsonPath('products.0.barcode', '6001234567890');
        $first->assertJsonPath('products.0.brand', 'HP');
        $first->assertJsonPath('products.0.unitPriceCents', 10000);
        $first->assertJsonPath('products.0.salePriceCents', 9000);
        $first->assertJsonPath('products.0.stockQuantity', 0);
        $first->assertJsonPath('products.0.stockStatus', 'out_of_stock');
        $first->assertJsonPath('products.0.published', true);
        $this->assertStringContainsString('Ports: 8', (string) $first->json('products.0.specifications'));

        $this->getJson('/api/store/catalogue?per_page=1&page=2', $headers)
            ->assertOk()
            ->assertJsonPath('products.0.sku', '')
            ->assertJsonPath('products.0.published', false)
            ->assertJsonPath('products.0.unitPriceCents', 0);

        $active->refresh();
        $this->assertTrue($active->updated_at->equalTo($stamp));
        $this->assertEquals(100, (float) $active->price);

        $this->getJson('/api/store/lookup?mpn=d11g8et', $headers)
            ->assertOk()
            ->assertJsonPath('sku', 'SW-24');
    }

    public function test_create_refuses_a_barcode_that_already_exists(): void
    {
        Product::factory()->create([
            'sku' => 'OLD-1',
            'barcode' => '6001234567890',
        ]);

        $this->postJson('/api/store/products', [
            'sku' => 'NEW-9',
            'name' => 'Duplicate barcode',
            'unitPriceCents' => 1000,
            'stockQuantity' => 1,
            'published' => true,
            'barcode' => '6001234567890',
        ], ['Authorization' => 'Bearer test-api-key'])->assertStatus(409);

        $this->assertDatabaseMissing('products', ['sku' => 'NEW-9']);
    }
}
