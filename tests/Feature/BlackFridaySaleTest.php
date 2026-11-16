<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\FeedService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlackFridaySaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_sale_shows_badge_was_price_and_charges_the_sale_price(): void
    {
        config(['shipping.flat_rate' => 150, 'shipping.free_threshold' => 1500]);

        $product = Product::factory()->create([
            'name' => 'Black Friday Laptop',
            'price' => 2000,
            'sale_price' => 1200,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addWeek(),
            'promo_label' => 'Black Friday',
            'short_description' => 'A business laptop for the sale window.',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('Black Friday', false)
            ->assertSee('40% off', false)
            ->assertSee('Was R 2,000.00', false)
            ->assertSee('R 1,200.00', false);

        $this->get(route('black-friday'))
            ->assertOk()
            ->assertSee('Black Friday Specials | Urban Focus', false)
            ->assertSee($product->name, false);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/black-friday', false);

        $this->withSession(['cart' => [$product->id => 1]])
            ->get(route('cart.index'))
            ->assertOk()
            ->assertSee('R 1,200.00', false);

        $this->withSession(['cart' => [$product->id => 1]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('R 1,200.00', false)
            ->assertSee('R 150.00', false);
    }

    public function test_expired_sale_uses_the_normal_price_and_leaves_the_specials_page(): void
    {
        $product = Product::factory()->create([
            'name' => 'Expired Sale Laptop',
            'price' => 2000,
            'sale_price' => 1200,
            'sale_starts_at' => now()->subWeeks(2),
            'sale_ends_at' => now()->subDay(),
            'promo_label' => 'Black Friday',
        ]);

        $this->get(route('products.show', $product))
            ->assertOk()
            ->assertSee('R 2,000.00', false)
            ->assertDontSee('Was R', false)
            ->assertDontSee('badge-black-friday', false);

        $this->get(route('black-friday'))
            ->assertOk()
            ->assertSee('No specials right now', false)
            ->assertDontSee($product->name, false);

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('/black-friday', false);
    }

    public function test_shipping_uses_the_sale_total_against_the_free_threshold(): void
    {
        config([
            'shipping.flat_rate' => 150,
            'shipping.free_threshold' => 1500,
            'paystack.secret_key' => 'sk_test_sale',
            'paystack.base_url' => 'https://api.paystack.co',
        ]);
        Mail::fake();
        Http::fake([
            'https://api.paystack.co/*' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://checkout.paystack.com/pay/sale',
                    'reference' => 'UF-SALE',
                ],
            ], 200),
        ]);

        $atThreshold = Product::factory()->create([
            'price' => 2000,
            'sale_price' => 1500,
            'sale_starts_at' => now()->subHour(),
            'sale_ends_at' => now()->addDay(),
        ]);
        $aboveThreshold = Product::factory()->create([
            'price' => 2000,
            'sale_price' => 1501,
            'sale_starts_at' => now()->subHour(),
            'sale_ends_at' => now()->addDay(),
        ]);

        $this->place($atThreshold);
        $this->place($aboveThreshold);

        $orders = Order::query()->orderBy('id')->get();
        $this->assertSame('1500.00', $orders[0]->subtotal);
        $this->assertSame('150.00', $orders[0]->shipping_cost);
        $this->assertSame('1500.00', OrderItem::query()->where('order_id', $orders[0]->id)->value('unit_price'));

        $this->assertSame('1501.00', $orders[1]->subtotal);
        $this->assertSame('0.00', $orders[1]->shipping_cost);
        $this->assertSame('1501.00', OrderItem::query()->where('order_id', $orders[1]->id)->value('unit_price'));
    }

    public function test_merchant_feed_includes_sale_price_only_while_a_sale_is_current_or_scheduled(): void
    {
        $active = $this->feedProduct('BF-ACTIVE', [
            'sale_price' => 800,
            'sale_starts_at' => now()->subDay(),
            'sale_ends_at' => now()->addDay(),
        ]);
        $scheduled = $this->feedProduct('BF-LATER', [
            'sale_price' => 700,
            'sale_starts_at' => now()->addDay(),
            'sale_ends_at' => now()->addDays(3),
        ]);
        $expired = $this->feedProduct('BF-OLD', [
            'sale_price' => 600,
            'sale_starts_at' => now()->subDays(10),
            'sale_ends_at' => now()->subDay(),
        ]);
        $regular = $this->feedProduct('BF-FULL', [
            'sale_price' => null,
        ]);

        $xml = app(FeedService::class)->googleMerchantXml();

        $activeItem = $this->feedItem($xml, $active->sku);
        $this->assertStringContainsString('g:price', $activeItem);
        $this->assertStringContainsString('g:sale_price ', $activeItem);
        $this->assertStringContainsString('g:sale_price_effective_date', $activeItem);
        $this->assertStringContainsString('+02:00', $activeItem);
        $this->assertStringContainsString('800.00 ZAR', $activeItem);
        $this->assertStringContainsString('1000.00 ZAR', $activeItem);

        $scheduledItem = $this->feedItem($xml, $scheduled->sku);
        $this->assertStringContainsString('g:sale_price ', $scheduledItem);
        $this->assertStringContainsString('g:sale_price_effective_date', $scheduledItem);

        $this->assertStringNotContainsString('g:sale_price', $this->feedItem($xml, $expired->sku));
        $this->assertStringNotContainsString('g:sale_price', $this->feedItem($xml, $regular->sku));

        $this->get(route('black-friday'))->assertDontSee($scheduled->name, false);
    }

    public function test_sale_commands_dry_run_and_restore_previous_values(): void
    {
        Storage::fake('local');

        $product = Product::factory()->create([
            'sku' => 'BF-CMD-1',
            'price' => 1000,
            'sale_price' => 800,
            'promo_label' => null,
        ]);
        $path = tempnam(sys_get_temp_dir(), 'uf-sale');
        file_put_contents($path, implode("\n", [
            'SKU,sale_price,sale_starts_at,sale_ends_at,promo_label',
            'BF-CMD-1,700,2026-11-16 00:00,2026-11-30 23:59,Black Friday',
            'MISSING-SKU,500,2026-11-16 00:00,2026-11-30 23:59,Black Friday',
        ]));

        Artisan::call('sale:apply', ['csv' => $path, '--dry-run' => true]);
        $product->refresh();
        $this->assertSame('800.00', $product->sale_price);
        $this->assertSame([], Storage::disk('local')->allFiles());

        Artisan::call('sale:apply', ['csv' => $path]);
        $product->refresh();
        $this->assertSame('700.00', $product->sale_price);
        $this->assertSame('Black Friday', $product->promo_label);
        $this->assertNotEmpty(Storage::disk('local')->allFiles());
        $this->assertStringContainsString('not_found', Artisan::output());

        Artisan::call('sale:clear', ['--label' => 'Black Friday', '--dry-run' => true]);
        $product->refresh();
        $this->assertSame('700.00', $product->sale_price);

        Artisan::call('sale:clear', ['--label' => 'Black Friday']);
        $product->refresh();
        $this->assertSame('800.00', $product->sale_price);
        $this->assertNull($product->promo_label);
        $this->assertNull($product->sale_starts_at);
        @unlink($path);
    }

    protected function place(Product $product): void
    {
        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), [
                'billing_first_name' => 'Thabo',
                'billing_last_name' => 'Mokoena',
                'billing_address_line_1' => '12 Long Street',
                'billing_city' => 'Johannesburg',
                'billing_province' => 'Gauteng',
                'billing_postal_code' => '2001',
                'customer_email' => 'thabo@example.com',
                'customer_phone' => '0821234567',
                'shipping_method' => 'courier',
                'payment_method' => 'paystack',
            ])
            ->assertRedirect();
    }

    protected function feedProduct(string $sku, array $overrides): Product
    {
        $product = Product::factory()->create(array_merge([
            'sku' => $sku,
            'name' => 'Feed '.$sku,
            'price' => 1000,
            'brand' => 'Lenovo',
            'short_description' => 'A stocked laptop with a normal catalogue description.',
        ], $overrides));

        ProductImage::query()->create([
            'product_id' => $product->id,
            'path' => 'products/'.$sku.'.jpg',
            'is_primary' => true,
            'alt_text' => $product->name,
        ]);

        return $product;
    }

    protected function feedItem(string $xml, string $sku): string
    {
        foreach (preg_split('/<item>/', $xml) as $item) {
            if (str_contains($item, '>'.$sku.'<')) {
                return $item;
            }
        }

        $this->fail('Feed item for '.$sku.' was not found.');
    }
}
