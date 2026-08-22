<?php

namespace Tests\Feature;

use App\Mail\OrderConfirmation;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'paystack.secret_key' => 'sk_test_checkout',
            'paystack.base_url' => 'https://api.paystack.co',
            'app.email' => 'sales@urbanfocus.test',
        ]);
    }

    public function test_guest_can_open_checkout_without_an_account(): void
    {
        $product = Product::factory()->create();

        $this->withSession(['cart' => [$product->id => 1]])
            ->get(route('checkout.index'))
            ->assertOk()
            ->assertSee('No account needed', false)
            ->assertSee('Continue to secure payment', false)
            ->assertDontSee('Favourites', false);
    }

    public function test_paystack_checkout_redirects_to_paystack_even_if_mail_fails(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP timeout'));
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://paystack.test/pay/abc',
                    'reference' => 'UF-TEST-REF',
                ],
            ], 200),
        ]);

        $product = Product::factory()->create(['price' => 500]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->validCheckoutPayload())
            ->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertSame('paystack', $order->payment_method);
        $this->assertSame('pending', $order->payment_status);

        $this->get(route('checkout.paystack.pay', $order))
            ->assertRedirect('https://paystack.test/pay/abc');
    }

    public function test_unpaid_paystack_order_can_retry_without_checkout_session(): void
    {
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://paystack.test/pay/retry',
                    'reference' => 'UF-RETRY-REF',
                ],
            ], 200),
        ]);

        $order = Order::create($this->orderAttributes());

        $this->get(route('checkout.paystack.pay', $order))
            ->assertRedirect('https://paystack.test/pay/retry')
            ->assertSessionMissing('errors');
    }

    public function test_paystack_init_failure_shows_retry_instead_of_breaking_checkout(): void
    {
        Mail::fake();
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => false,
                'message' => 'Unable to process',
            ], 400),
        ]);

        $product = Product::factory()->create(['price' => 500]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->validCheckoutPayload())
            ->assertRedirect();

        $order = Order::firstOrFail();

        $this->get(route('checkout.paystack.pay', $order))
            ->assertRedirect(route('checkout.success', $order));

        $this->get(route('checkout.success', $order))
            ->assertOk()
            ->assertSee('Pay now with Paystack', false)
            ->assertSee('Finish your payment', false);
    }

    public function test_successful_paystack_order_sends_confirmation_email(): void
    {
        Mail::fake();
        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => [
                    'authorization_url' => 'https://paystack.test/pay/ok',
                    'reference' => 'UF-OK',
                ],
            ], 200),
        ]);

        $product = Product::factory()->create(['price' => 500]);

        $this->withSession(['cart' => [$product->id => 1]])
            ->post(route('checkout.store'), $this->validCheckoutPayload())
            ->assertRedirect();

        Mail::assertSent(OrderConfirmation::class);
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_unavailable_cart_item_blocks_checkout(): void
    {
        $product = Product::factory()->unavailable()->create();

        $this->withSession(['cart' => [$product->id => 1]])
            ->get(route('checkout.index'))
            ->assertRedirect(route('cart.index'));
    }

    /** @return array<string, string> */
    protected function validCheckoutPayload(): array
    {
        return [
            'billing_first_name' => 'Thabo',
            'billing_last_name' => 'Mokoena',
            'billing_address_line_1' => '12 Long Street',
            'billing_city' => 'Johannesburg',
            'billing_province' => 'Gauteng',
            'billing_postal_code' => '2001',
            'customer_email' => 'thabo@example.com',
            'customer_phone' => '082 123 4567',
            'shipping_method' => 'courier',
            'payment_method' => 'paystack',
        ];
    }

    /** @return array<string, mixed> */
    protected function orderAttributes(): array
    {
        return [
            'order_number' => 'UF-RETRY01',
            'status' => 'pending',
            'payment_method' => 'paystack',
            'payment_status' => 'pending',
            'shipping_method' => 'courier',
            'subtotal' => 500,
            'shipping_cost' => 99,
            'tax_amount' => 78.13,
            'discount_amount' => 0,
            'total' => 599,
            'customer_email' => 'retry@example.com',
            'customer_phone' => '0821234567',
            'billing_first_name' => 'Retry',
            'billing_last_name' => 'Customer',
            'billing_address_line_1' => '1 Main Road',
            'billing_city' => 'Cape Town',
            'billing_province' => 'Western Cape',
            'billing_postal_code' => '8001',
            'billing_country' => 'ZA',
        ];
    }
}
