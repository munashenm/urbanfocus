<?php

namespace App\Services\Chat;

use App\Models\ChatAgentPresence;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChatLiveService
{
    public function __construct(protected ChatAgentChannel $channel) {}

    public function notifyQueued(ChatConversation $conversation): void
    {
        $this->channel->notifyQueued($conversation);
    }

    public function setPresence(User $user, string $status): ChatAgentPresence
    {
        $status = $status === 'online' ? 'online' : 'away';

        return ChatAgentPresence::query()->updateOrCreate(
            ['user_id' => $user->id],
            ['status' => $status, 'last_seen_at' => now()],
        );
    }

    public function presenceFor(User $user): ChatAgentPresence
    {
        return ChatAgentPresence::query()->firstOrCreate(
            ['user_id' => $user->id],
            ['status' => 'away', 'last_seen_at' => now()],
        );
    }

    public function claim(ChatConversation $conversation, User $agent): ChatConversation
    {
        $claimed = DB::transaction(function () use ($conversation, $agent) {
            $updated = ChatConversation::query()
                ->whereKey($conversation->id)
                ->where('status', 'waiting_staff')
                ->whereNull('assigned_user_id')
                ->update([
                    'assigned_user_id' => $agent->id,
                    'claimed_at' => now(),
                    'status' => 'live',
                    'bot_paused' => true,
                    'staff_read_at' => now(),
                ]);

            return $updated === 1;
        });

        if (! $claimed) {
            throw ValidationException::withMessages([
                'conversation' => 'Another agent already has this chat.',
            ]);
        }

        $conversation->refresh();
        $this->notice($conversation, $agent->name.' has joined the chat.');

        return $conversation->fresh();
    }

    public function reply(ChatConversation $conversation, User $agent, string $body): ChatMessage
    {
        $body = trim(strip_tags($body));
        if ($body === '') {
            throw ValidationException::withMessages(['body' => 'Write a reply first.']);
        }

        if ((int) $conversation->assigned_user_id !== (int) $agent->id && ! $agent->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'conversation' => 'Claim this chat before you reply.',
            ]);
        }

        if ($conversation->status === 'closed') {
            throw ValidationException::withMessages([
                'conversation' => 'This chat is closed.',
            ]);
        }

        $message = $conversation->messages()->create([
            'sender' => 'staff',
            'user_id' => $agent->id,
            'kind' => 'message',
            'body' => $body,
        ]);
        $conversation->forceFill([
            'status' => 'live',
            'assigned_user_id' => $conversation->assigned_user_id ?: $agent->id,
            'claimed_at' => $conversation->claimed_at ?: now(),
            'bot_paused' => true,
            'staff_read_at' => now(),
            'last_message_at' => $message->created_at ?? now(),
        ])->save();

        return $message;
    }

    public function handBack(ChatConversation $conversation, User $agent): ChatConversation
    {
        $this->guardOwner($conversation, $agent);
        $conversation->forceFill([
            'status' => 'open',
            'assigned_user_id' => null,
            'bot_paused' => false,
            'staff_read_at' => now(),
        ])->save();
        $this->notice($conversation, $agent->name.' handed the chat back to the assistant.');

        return $conversation->fresh();
    }

    public function close(ChatConversation $conversation, User $agent): ChatConversation
    {
        $this->guardOwner($conversation, $agent);
        $conversation->forceFill([
            'status' => 'closed',
            'bot_paused' => false,
            'staff_read_at' => now(),
        ])->save();
        $this->notice($conversation, 'This chat is closed.');

        return $conversation->fresh();
    }

    public function notifyAssigned(ChatConversation $conversation, ChatMessage $message): void
    {
        if ($conversation->status !== 'live') {
            return;
        }

        $conversation->loadMissing('assignedAgent');
        $this->channel->notifyCustomerMessage($conversation, $message);
    }

    protected function guardOwner(ChatConversation $conversation, User $agent): void
    {
        if ((int) $conversation->assigned_user_id === (int) $agent->id || $agent->isSuperAdmin()) {
            return;
        }

        throw ValidationException::withMessages([
            'conversation' => 'Only the agent in this chat can do that.',
        ]);
    }

    protected function notice(ChatConversation $conversation, string $body): void
    {
        $message = $conversation->messages()->create([
            'sender' => 'system',
            'kind' => 'notice',
            'body' => $body,
        ]);
        $conversation->forceFill(['last_message_at' => $message->created_at ?? now()])->save();
    }
}
