<?php

namespace App\Services\Chat;

use App\Mail\ChatAgentQueueAlert;
use App\Models\ChatAgentPresence;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class EmailChatAgentChannel implements ChatAgentChannel
{
    public function notifyQueued(ChatConversation $conversation): void
    {
        foreach ($this->onlineAgents() as $agent) {
            $this->send($agent->email, $conversation, null);
        }
    }

    public function notifyCustomerMessage(ChatConversation $conversation, ChatMessage $message): void
    {
        $agent = $conversation->assignedAgent;
        if (! $agent || blank($agent->email)) {
            return;
        }

        $this->send($agent->email, $conversation, $message);
    }

    /**
     * @return list<User>
     */
    protected function onlineAgents(): array
    {
        $ids = ChatAgentPresence::query()->where('status', 'online')->pluck('user_id');

        return User::query()
            ->whereIn('id', $ids)
            ->where('is_active', true)
            ->whereNotNull('email')
            ->get()
            ->all();
    }

    protected function send(string $email, ChatConversation $conversation, ?ChatMessage $message): void
    {
        try {
            Mail::to($email)->send(new ChatAgentQueueAlert($conversation, $message));
        } catch (Throwable $e) {
            Log::warning('Live chat agent email failed', ['error' => $e->getMessage()]);
        }
    }
}
