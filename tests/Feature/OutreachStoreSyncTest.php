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
}
