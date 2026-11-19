<?php

namespace Tests\Feature;

use App\Mail\ChatHandoverAlert;
use App\Mail\ChatWeeklySummary;
use App\Mail\NewQuoteNotification;
use App\Models\ChatConversation;
use App\Models\ChatInsight;
use App\Models\Product;
use App\Models\Quote;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ChatOperationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'Africa/Johannesburg'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_working_hours_handover_alerts_staff_once_and_is_honest(): void
    {
        Mail::fake();
        Http::fake();

        $this->postJson(route('chat.handover'), [
            'name' => 'Thandi Mokoena',
            'email' => 'thandi@example.com',
        ])->assertOk()
            ->assertJsonPath('availability.open', true)
            ->assertSee('not necessarily straight away', false)
            ->assertSee('087 550 1813', false)
            ->assertDontSee('WhatsApp', false);

        Mail::assertSent(ChatHandoverAlert::class, function (ChatHandoverAlert $mail) {
            $html = $mail->render();

            return $mail->envelope()->subject === 'Urban Focus chat: Thandi Mokoena wants to talk to the team'
                && str_contains($html, 'Thandi Mokoena')
                && str_contains($html, 'thandi@example.com')
                && str_contains($html, 'talk to the team')
                && str_contains($html, route('admin.chat.show', ChatConversation::query()->first()));
        });

        $this->postJson(route('chat.handover'), [
            'name' => 'Thandi Mokoena',
            'email' => 'thandi@example.com',
        ])->assertOk();

        Mail::assertSent(ChatHandoverAlert::class, 1);
        Http::assertNothingSent();
    }

    public function test_configured_sms_is_sent_once_and_skipped_when_disabled(): void
    {
        Mail::fake();
        Http::fake([
            'https://api.bulksms.com/*' => Http::response(['id' => 'sms-1'], 201),
        ]);
        config([
            'chat.sms.enabled' => true,
            'chat.sms.driver' => 'bulksms',
            'chat.sms.to' => '+27820000000',
            'chat.sms.bulksms.token_id' => 'token-id',
            'chat.sms.bulksms.token_secret' => 'token-secret',
            'chat.sms.bulksms.url' => 'https://api.bulksms.com/v1/messages',
        ]);

        $this->postJson(route('chat.messages'), [
            'body' => 'Please can I speak to someone',
            'name' => 'Thandi Mokoena',
            'email' => 'thandi@example.com',
        ])->assertOk()
            ->assertSee('not necessarily straight away', false);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.bulksms.com')
                && str_contains($request->body(), '27820000000')
                && str_contains($request->body(), 'Thandi Mokoena');
        });

        $this->postJson(route('chat.messages'), [
            'body' => 'I still want to talk to a person',
        ])->assertOk();

        Http::assertSentCount(1);
    }

    public function test_offline_handover_still_asks_for_contact_and_sets_expectations(): void
    {
        Mail::fake();
        Carbon::setTestNow(Carbon::parse('2026-11-22 10:00:00', 'Africa/Johannesburg'));

        $this->postJson(route('chat.handover'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $this->postJson(route('chat.handover'), [
            'name' => 'Thandi Mokoena',
            'email' => 'thandi@example.com',
        ])->assertOk()
            ->assertSee('We are offline until', false)
            ->assertSee('not an immediate reply', false)
            ->assertSee("Thanks, we'll get back to you by", false);

        Mail::assertSent(ChatHandoverAlert::class, 1);
    }

    public function test_chat_quote_is_confirmed_then_stored_with_the_normal_quotes(): void
    {
        Mail::fake();
        Product::factory()->create([
            'name' => 'UniFi Switch Ultra',
            'sku' => 'USW-ULTRA',
            'brand' => 'Ubiquiti',
            'price' => 1999,
        ]);

        $this->postJson(route('chat.messages'), [
            'body' => 'Please get a quote for 4 x USW-ULTRA',
        ])->assertOk()->assertSee('company name', false)->assertDontSee('R 1,999.00', false);

        $this->postJson(route('chat.messages'), ['body' => 'Acme Networks'])->assertOk()->assertSee('contact name', false);
        $this->postJson(route('chat.messages'), ['body' => 'Sam Naidoo'])->assertOk()->assertSee('email', false);
        $this->postJson(route('chat.messages'), ['body' => 'sam@acme.co.za'])->assertOk()->assertSee('phone', false);
        $this->postJson(route('chat.messages'), ['body' => 'skip'])->assertOk()->assertSee('area', false);
        $this->postJson(route('chat.messages'), ['body' => 'Johannesburg'])->assertOk()->assertSee('notes', false);

        $summary = $this->postJson(route('chat.messages'), ['body' => 'skip'])->assertOk();
        $summaryBody = collect($summary->json('messages'))->where('sender', 'assistant')->last()['body'];
        $this->assertStringContainsString('4 × UniFi Switch Ultra', $summaryBody);
        $this->assertStringContainsString('SKU USW-ULTRA', $summaryBody);
        $this->assertStringContainsString('Acme Networks', $summaryBody);
        $this->assertStringContainsString('No price is quoted and nothing is approved', $summaryBody);
        $this->assertStringNotContainsString('R 1,999.00', $summaryBody);
        $this->assertSame(0, Quote::query()->count());

        $this->postJson(route('chat.messages'), ['body' => 'how much will it cost?'])
            ->assertOk()
            ->assertSee('No price is quoted', false)
            ->assertDontSee('R 1,999.00', false);

        $sent = $this->postJson(route('chat.messages'), ['body' => 'yes'])->assertOk();
        $sent->assertSee('No price has been quoted and nothing is approved', false);
        $sent->assertDontSee('R 1,999.00', false);

        $quote = Quote::query()->firstOrFail();
        $this->assertSame('quote', $quote->type);
        $this->assertSame('Sam Naidoo', $quote->name);
        $this->assertSame('Acme Networks', $quote->company);
        $this->assertSame('sam@acme.co.za', $quote->email);
        $this->assertNull($quote->phone);
        $this->assertSame('Johannesburg', $this->between($quote->message, 'Delivery area: ', "\n"));
        $this->assertStringContainsString('Submitted from customer chat', $quote->message);
        $this->assertStringContainsString('4 × UniFi Switch Ultra', $quote->message);
        $this->assertStringNotContainsString('1,999', $quote->message);

        Mail::assertSent(NewQuoteNotification::class, fn (NewQuoteNotification $mail) => $mail->quote->is($quote));
    }

    public function test_missing_product_search_is_logged_for_the_weekly_summary(): void
    {
        Mail::fake();

        $this->postJson(route('chat.messages'), [
            'body' => 'Do you have an HP laptop called Zyrix?',
        ])->assertOk()->assertSee('could not find a matching product', false);

        $this->assertDatabaseHas('chat_insights', [
            'kind' => 'not_found',
        ]);

        $insight = ChatInsight::query()->where('kind', 'not_found')->firstOrFail();
        $this->assertStringContainsString('Zyrix', $insight->question);

        $this->artisan('chat:weekly-summary')->assertSuccessful();

        Mail::assertSent(ChatWeeklySummary::class, function (ChatWeeklySummary $mail) {
            $html = $mail->render();

            return str_contains($html, 'Zyrix')
                && str_contains($html, 'Products searched for but not found');
        });

        $event = collect(app(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'chat:weekly-summary'));

        $this->assertNotNull($event);
        $this->assertSame('0 8 * * 1', $event->expression);
        $this->assertSame('Africa/Johannesburg', $event->timezone);
    }

    public function test_staff_can_delete_a_conversation_and_a_viewer_cannot(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $this->postJson(route('chat.messages'), ['body' => 'Hello'])->assertOk();
        $conversation = ChatConversation::query()->firstOrFail();

        $viewer = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $viewer->syncRoles(['viewer']);

        $this->actingAs($viewer)
            ->delete(route('admin.chat.destroy', $conversation))
            ->assertForbidden();
        $this->assertDatabaseHas('chat_conversations', ['id' => $conversation->id]);

        $support = User::factory()->create(['is_admin' => true, 'is_active' => true]);
        $support->syncRoles(['support']);

        $this->actingAs($support)
            ->delete(route('admin.chat.destroy', $conversation))
            ->assertRedirect(route('admin.chat.index'));

        $this->assertDatabaseMissing('chat_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('chat_messages', ['conversation_id' => $conversation->id]);
    }

    private function between(string $text, string $start, string $end): string
    {
        $from = strpos($text, $start);
        $this->assertNotFalse($from);
        $from += strlen($start);
        $to = strpos($text, $end, $from);
        $this->assertNotFalse($to);

        return substr($text, $from, $to - $from);
    }
}
