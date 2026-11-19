<?php

namespace Tests\Feature;

use App\Mail\ChatStaffAlert;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Chat\ChatPolicyGuide;
use App\Services\Chat\ChatService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'Africa/Johannesburg'));
        config([
            'chat.openai.enabled' => false,
            'chat.openai.api_key' => null,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_storefront_widget_offers_quick_help_and_honest_hours(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('id="uf-chat"', false)
            ->assertSee('class="uf-chat-launcher"', false)
            ->assertSee('Talk to the team', false)
            ->assertSee('data-uf-quick="search"', false)
            ->assertSee('Search products', false)
            ->assertSee('data-uf-quick="quote"', false)
            ->assertSee('Request a quote', false)
            ->assertSee('data-uf-quick="track"', false)
            ->assertSee('Track an order', false)
            ->assertSee('data-uf-quick="contact"', false)
            ->assertSee(route('orders.track'), false)
            ->assertSee(route('b2b.quote'), false)
            ->assertSee('Hours open', false)
            ->assertSee('sales@urbanfocus.co.za', false)
            ->assertSee('087 550 1813', false)
            ->assertSee('It is not approved from this chat', false)
            ->assertSee(route('warranty'), false)
            ->assertSee(route('returns'), false)
            ->assertDontSee('online now', false)
            ->assertDontSee('Our team is offline', false);

        $css = file_get_contents(public_path('css/app.css'));
        $this->assertIsString($css);
        $this->assertStringContainsString('width: 380px', $css);
        $this->assertStringContainsString('@media (max-width: 640px)', $css);
        $this->assertStringContainsString('.uf-chat-panel', $css);
        $this->assertStringNotContainsString('SEO', file_get_contents(resource_path('views/partials/chat-widget.blade.php')));
    }

    public function test_offline_hours_keep_a_direct_contact_option_and_alert_staff(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-22 10:00:00', 'Africa/Johannesburg'));

        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('Offline', false)
            ->assertSee('Our team is offline', false)
            ->assertSee('sales@urbanfocus.co.za', false)
            ->assertDontSee('Hours open', false);

        $this->postJson(route('chat.messages'), ['body' => 'Please call me about a laptop'])
            ->assertOk()
            ->assertJsonPath('availability.open', false)
            ->assertJsonPath('messages.0.body', 'Please call me about a laptop');

        Mail::assertSent(ChatStaffAlert::class);
        $this->assertDatabaseHas('chat_messages', ['body' => 'Please call me about a laptop']);
    }

    public function test_staff_can_read_and_reply_and_a_viewer_cannot(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->postJson(route('chat.messages'), ['body' => 'I need a person to check a quote'])
            ->assertOk();

        $conversation = ChatConversation::query()->firstOrFail();

        $support = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $support->syncRoles(['support']);

        $this->actingAs($support)
            ->get(route('admin.chat.index'))
            ->assertOk()
            ->assertSee('Customer chat', false)
            ->assertSee('Unread', false);

        $this->actingAs($support)
            ->get(route('admin.chat.show', $conversation))
            ->assertOk()
            ->assertSee('I need a person to check a quote', false);

        $this->actingAs($support)
            ->post(route('admin.chat.reply', $conversation), [
                'body' => 'We can collect the details on a call.',
            ])
            ->assertRedirect(route('admin.chat.show', $conversation));

        $this->getJson(route('chat.session'))
            ->assertOk()
            ->assertSee('We can collect the details on a call.', false);

        $viewer = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $viewer->syncRoles(['viewer']);

        $this->actingAs($viewer)
            ->post(route('admin.chat.reply', $conversation), ['body' => 'Viewer reply'])
            ->assertForbidden();

        auth()->logout();

        $this->get(route('admin.chat.index'))->assertRedirect(route('login'));
    }

    public function test_order_tracking_requires_both_secrets_and_hides_other_customers(): void
    {
        Http::fake();

        $order = Order::create([
            'order_number' => 'UF-PRIV001',
            'status' => 'processing',
            'payment_method' => 'paystack',
            'payment_status' => 'paid',
            'shipping_method' => 'courier',
            'subtotal' => 1000,
            'shipping_cost' => 99,
            'tax_amount' => 0,
            'discount_amount' => 0,
            'total' => 1099,
            'customer_email' => 'owner@example.com',
            'customer_phone' => '0820000000',
            'billing_first_name' => 'Secret',
            'billing_last_name' => 'Person',
            'billing_address_line_1' => '99 Hidden Road',
            'billing_city' => 'Cape Town',
            'billing_province' => 'Western Cape',
            'billing_postal_code' => '8001',
            'billing_country' => 'ZA',
        ]);
        $order->items()->create([
            'product_name' => 'Private Switch',
            'product_sku' => 'UF-PRIV',
            'unit_price' => 500,
            'quantity' => 2,
            'line_total' => 1000,
        ]);

        $this->postJson(route('chat.track'), ['order_number' => 'UF-PRIV001'])
            ->assertStatus(422);

        $this->postJson(route('chat.track'), ['email' => 'owner@example.com'])
            ->assertStatus(422);

        $wrong = $this->postJson(route('chat.track'), [
            'order_number' => 'UF-PRIV001',
            'email' => 'someoneelse@example.com',
        ])->assertOk();

        $wrong->assertSee('No order found with those details', false);
        $wrong->assertDontSee('Secret', false);
        $wrong->assertDontSee('99 Hidden Road', false);
        $wrong->assertDontSee('owner@example.com', false);
        $wrong->assertDontSee('0820000000', false);

        $this->flushSession();

        $right = $this->postJson(route('chat.track'), [
            'order_number' => 'uf-priv001',
            'email' => 'OWNER@example.com',
        ])->assertOk();

        $right->assertSee('UF-PRIV001', false);
        $right->assertSee('Private Switch', false);
        $right->assertSee('R 1,099.00', false);
        $right->assertDontSee('99 Hidden Road', false);
        $right->assertDontSee('owner@example.com', false);
        $right->assertDontSee('Secret Person', false);

        $public = json_encode($right->json('messages'));
        $this->assertIsString($public);
        $this->assertStringNotContainsString('owner@example.com', $public);

        $this->flushSession();
        $this->getJson(route('chat.session'))
            ->assertOk()
            ->assertJsonCount(0, 'messages')
            ->assertDontSee('UF-PRIV001', false)
            ->assertDontSee('Private Switch', false);

        $this->post(route('orders.track.lookup'), [
            'order_number' => 'UF-PRIV001',
            'email' => 'someoneelse@example.com',
        ])->assertDontSee('Secret', false)
            ->assertDontSee('99 Hidden Road', false);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));
    }

    public function test_product_answers_use_the_catalogue_when_openai_is_unavailable(): void
    {
        Mail::fake();
        Http::fake();

        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'sku' => 'USW-ULTRA',
            'brand' => 'Ubiquiti',
            'price' => 1999,
            'sale_price' => null,
            'stock_quantity' => 4,
            'manage_stock' => true,
            'in_stock' => true,
            'warranty_months' => 24,
            'specifications' => ['Ports' => '8'],
        ]);
        Product::factory()->unavailable()->create([
            'name' => 'Spare Bracket',
            'sku' => 'BRK-NONE',
            'price' => 50,
        ]);

        $response = $this->postJson(route('chat.messages'), [
            'body' => 'What is the price and availability of the UniFi Switch Ultra?',
        ])->assertOk();

        $response->assertSee('UniFi Switch Ultra', false);
        $response->assertSee('R 1,999.00', false);
        $response->assertSee('In Stock', false);
        $response->assertSee('Ports: 8', false);
        $response->assertSee('2 year manufacturer warranty', false);
        $response->assertSee('not a confirmed delivery date', false);
        $response->assertDontSee('R 50.00', false);
        $response->assertDontSee('BRK-NONE', false);

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));
        Mail::assertNotSent(ChatStaffAlert::class);
    }

    public function test_openai_reply_is_kept_only_when_it_matches_the_catalogue_and_omits_private_details(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'The UniFi Switch Ultra (SKU USW-ULTRA) is R 1,999.00. Availability: In Stock.'],
                ]],
            ], 200),
        ]);

        config([
            'chat.openai.enabled' => true,
            'chat.openai.api_key' => 'sk-test-not-a-real-key',
            'chat.openai.base_url' => 'https://api.openai.com/v1',
            'chat.openai.model' => 'gpt-4o-mini',
        ]);

        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'sku' => 'USW-ULTRA',
            'price' => 1999,
            'stock_quantity' => 4,
            'manage_stock' => true,
            'in_stock' => true,
            'specifications' => ['Ports' => '8'],
        ]);

        $response = $this->postJson(route('chat.messages'), [
            'body' => 'Price of the UniFi Switch Ultra? Email secret@example.com, phone 0821234567, address 99 Hidden Road.',
        ])->assertOk();

        $response->assertSee('SKU USW-ULTRA', false);
        $response->assertSee('R 1,999.00', false);

        Http::assertSent(function ($request) {
            $this->assertStringNotContainsString('secret@example.com', $request->body());
            $this->assertStringNotContainsString('0821234567', $request->body());
            $this->assertStringNotContainsString('99 Hidden Road', $request->body());
            $this->assertStringNotContainsString('sk-test-not-a-real-key', $request->body());

            return str_contains($request->url(), '/chat/completions');
        });
    }

    public function test_invented_openai_prices_and_failed_requests_fall_back_to_the_catalogue(): void
    {
        Http::fake([
            'https://api.openai.com/*' => Http::response([
                'choices' => [[
                    'message' => ['content' => 'It is 50% off at R 10.00 and arrives on 3 December.'],
                ]],
            ], 200),
        ]);

        config([
            'chat.openai.enabled' => true,
            'chat.openai.api_key' => 'sk-test-not-a-real-key',
            'chat.openai.base_url' => 'https://api.openai.com/v1',
        ]);

        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'sku' => 'USW-ULTRA',
            'price' => 1999,
            'specifications' => ['Ports' => '8'],
        ]);

        $invented = $this->postJson(route('chat.messages'), [
            'body' => 'Price of the UniFi Switch Ultra',
        ])->assertOk();

        $invented->assertSee('R 1,999.00', false);
        $invented->assertSee('Ports: 8', false);
        $invented->assertDontSee('50%', false);
        $invented->assertDontSee('R 10.00', false);
        $invented->assertDontSee('December', false);

        $this->flushSession();

        Http::fake([
            'https://api.openai.com/*' => Http::response(['error' => 'down'], 500),
        ]);

        $failed = $this->postJson(route('chat.messages'), [
            'body' => 'Price of the UniFi Switch Ultra',
        ])->assertOk();

        $failed->assertSee('R 1,999.00', false);
        $failed->assertDontSee('50%', false);
    }

    public function test_warranty_and_return_requests_are_sent_to_staff_without_approval(): void
    {
        Mail::fake();
        Http::fake();
        $this->seed(RolePermissionSeeder::class);

        $missing = $this->postJson(route('chat.requests'), [
            'type' => 'warranty',
            'name' => 'Amina Naidoo',
            'email' => 'amina@example.com',
            'details' => 'The unit does not power on.',
        ]);
        $missing->assertStatus(422);

        $response = $this->postJson(route('chat.requests'), [
            'type' => 'warranty',
            'name' => 'Amina Naidoo',
            'email' => 'amina@example.com',
            'order_number' => 'UF-WARR01',
            'product' => 'UniFi Switch Ultra',
            'serial_number' => 'SN-SECRET-9',
            'details' => 'The unit does not power on.',
        ])->assertOk();

        $response->assertSee('It is not approved', false);
        $response->assertSee('14 days of delivery', false);
        $response->assertSee('return authorisation reference', false);
        $response->assertDontSee('amina@example.com', false);
        $response->assertDontSee('SN-SECRET-9', false);
        $response->assertDontSee('approved the', false);

        $this->assertDatabaseHas('chat_messages', [
            'kind' => 'warranty_request',
            'review_status' => 'submitted',
        ]);
        $this->assertDatabaseMissing('chat_messages', ['review_status' => 'approved']);

        $message = ChatMessage::query()->where('kind', 'warranty_request')->firstOrFail();
        $this->assertSame('SN-SECRET-9', $message->payload['serial_number']);

        $support = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $support->syncRoles(['support']);

        $this->actingAs($support)
            ->get(route('admin.chat.show', $message->conversation_id))
            ->assertOk()
            ->assertSee('SN-SECRET-9', false)
            ->assertSee('amina@example.com', false)
            ->assertDontSee('>Approved<', false);

        $this->actingAs($support)
            ->post(route('admin.chat.requests.update', [$message->conversation_id, $message]), [
                'review_status' => 'approved',
            ])
            ->assertSessionHasErrors('review_status');

        $this->assertSame('submitted', $message->fresh()->review_status);

        $this->actingAs($support)
            ->post(route('admin.chat.requests.update', [$message->conversation_id, $message]), [
                'review_status' => 'in_review',
            ])
            ->assertRedirect();

        $this->assertSame('in_review', $message->fresh()->review_status);

        $this->flushSession();
        auth()->logout();

        $this->postJson(route('chat.requests'), [
            'type' => 'return',
            'name' => 'Amina Naidoo',
            'email' => 'amina@example.com',
            'order_number' => 'UF-RET01',
            'condition' => 'unused',
            'details' => 'The box was short one bracket.',
        ])->assertOk()
            ->assertSee('does not approve a return or a refund', false)
            ->assertSee('14 calendar days', false);

        Mail::assertSent(ChatStaffAlert::class);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'openai.com'));

        $guide = app(ChatPolicyGuide::class);
        $returnsPage = file_get_contents(resource_path('views/pages/returns.blade.php'));
        $warrantyPage = file_get_contents(resource_path('views/pages/warranty.blade.php'));
        $this->assertStringContainsString('14 calendar days', $returnsPage);
        $this->assertStringContainsString('14 calendar days', $guide->returnsSummary());
        $this->assertStringContainsString('do not send items without a return authorisation reference', $warrantyPage);
        $this->assertStringContainsString('return authorisation reference', $guide->warrantySummary());
    }

    public function test_handover_and_comparison_stay_on_verified_products(): void
    {
        Http::fake();

        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'sku' => 'USW-ULTRA',
            'brand' => 'Ubiquiti',
            'price' => 1999,
        ]);
        Product::factory()->create([
            'name' => 'Dell Latitude 5440',
            'sku' => 'LAT-5440',
            'brand' => 'Dell',
            'price' => 18000,
            'sale_price' => 17000,
        ]);

        $compared = $this->postJson(route('chat.messages'), [
            'body' => 'Compare the UniFi Switch versus the Dell Latitude',
        ])->assertOk();

        $compared->assertSee('UniFi Switch Ultra', false);
        $compared->assertSee('Dell Latitude 5440', false);
        $compared->assertSee('R 1,999.00', false);
        $compared->assertSee('R 17,000.00', false);

        $this->postJson(route('chat.handover'))
            ->assertOk()
            ->assertSee('A person on the Urban Focus team', false);

        $this->assertSame('waiting_staff', ChatConversation::query()->first()->status);
        $redacted = app(ChatService::class)->redact('secret@example.com 99 Hidden Road');
        $this->assertStringNotContainsString('secret@example.com', $redacted);
        $this->assertStringNotContainsString('99 Hidden Road', $redacted);
    }
}
