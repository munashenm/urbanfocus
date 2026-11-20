<?php

namespace Tests\Feature;

use App\Mail\ChatAgentQueueAlert;
use App\Mail\ChatHandoverAlert;
use App\Models\ChatAgentPresence;
use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LiveChatTest extends TestCase
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

    public function test_urgent_message_asks_a_clarifying_question_instead_of_refusing(): void
    {
        $this->postJson(route('chat.messages'), ['body' => 'hi'])->assertOk()->assertSee('What do you need?', false);

        $urgent = $this->postJson(route('chat.messages'), ['body' => 'urgent please'])->assertOk();
        $body = collect($urgent->json('messages'))->where('sender', 'assistant')->last()['body'];

        $this->assertStringContainsString('live agent', $body);
        $this->assertStringContainsString('quote', $body);
        $this->assertStringNotContainsString('I cannot answer that', $body);
        $this->assertStringNotContainsString('WhatsApp', $body);
    }

    public function test_agent_can_claim_a_queued_chat_and_the_bot_stays_quiet(): void
    {
        Mail::fake();

        $this->postJson(route('chat.messages'), ['body' => 'I need to talk to a human'])->assertOk()
            ->assertSee('not necessarily straight away', false)
            ->assertSee('queue', false);

        $conversation = ChatConversation::query()->firstOrFail();
        $this->assertSame('waiting_staff', $conversation->status);
        $this->assertTrue($conversation->bot_paused);
        Mail::assertSent(ChatHandoverAlert::class, 1);

        $agent = User::factory()->create([
            'name' => 'Victor',
            'is_admin' => true,
            'is_active' => true,
        ]);
        $agent->syncRoles(['chat_agent']);
        ChatAgentPresence::query()->create([
            'user_id' => $agent->id,
            'status' => 'online',
            'last_seen_at' => now(),
        ]);

        $this->postJson(route('chat.messages'), ['body' => 'Please connect me to the team'])->assertOk();
        Mail::assertSent(ChatAgentQueueAlert::class);

        $token = session('chat_public_token');

        $this->actingAs($agent)
            ->postJson(route('admin.live-chat.claim', $conversation))
            ->assertOk()
            ->assertSee('Victor has joined the chat', false);

        auth()->logout();
        session(['chat_public_token' => $token]);

        $followUp = $this->postJson(route('chat.messages'), ['body' => 'Do you have an HP laptop in stock?'])->assertOk();
        $assistants = collect($followUp->json('messages'))->where('sender', 'assistant');
        $this->assertTrue($assistants->every(fn ($message) => ! str_contains($message['body'], 'HP')));

        $this->actingAs($agent)
            ->postJson(route('admin.live-chat.reply', $conversation), ['body' => 'Victor here. I can check that with you.'])
            ->assertOk();

        auth()->logout();
        session(['chat_public_token' => $token]);

        $this->getJson(route('chat.session'))
            ->assertOk()
            ->assertSee('Victor here. I can check that with you.', false)
            ->assertSee('Victor', false);
    }

    public function test_agent_channel_stays_closed_until_a_token_is_set(): void
    {
        config(['chat.agent_channel.token' => null]);
        $this->postJson(route('chat.agent-channel'), [])->assertNotFound();

        $agent = User::factory()->create([
            'name' => 'Sbu',
            'email' => 'sbu@example.com',
            'is_admin' => true,
            'is_active' => true,
        ]);
        $agent->syncRoles(['chat_agent']);

        $this->postJson(route('chat.messages'), ['body' => 'Please talk to a person'])->assertOk();
        $conversation = ChatConversation::query()->firstOrFail();

        config(['chat.agent_channel.token' => 'channel-secret']);

        $this->postJson(route('chat.agent-channel'), [
            'conversation_id' => $conversation->id,
            'agent_email' => 'sbu@example.com',
            'action' => 'claim',
        ], ['X-Chat-Channel-Token' => 'wrong'])->assertNotFound();

        $this->postJson(route('chat.agent-channel'), [
            'conversation_id' => $conversation->id,
            'agent_email' => 'sbu@example.com',
            'action' => 'claim',
        ], ['X-Chat-Channel-Token' => 'channel-secret'])->assertOk();

        $this->assertSame('live', $conversation->fresh()->status);
        $this->assertSame($agent->id, $conversation->fresh()->assigned_user_id);
    }

    public function test_agent_command_creates_a_login_without_writing_the_password_into_the_database_as_text(): void
    {
        $this->artisan('chat:agents', [
            '--email' => ['Charity=charity@example.com'],
        ])->assertSuccessful()->expectsOutputToContain('Password:');

        $user = User::query()->where('email', 'charity@example.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('Charity', $user->name);
        $this->assertTrue($user->hasPermission('chat.agent'));
        $this->assertStringNotContainsString('Password:', $user->password);
    }
}
