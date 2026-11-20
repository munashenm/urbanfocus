<?php

namespace App\Services\Chat;

use App\Models\ChatConversation;
use App\Models\ChatMessage;

/**
 * Outbound notice when a customer is waiting or writes during a live chat.
 * A later phone channel can implement this without changing the chat itself.
 */
interface ChatAgentChannel
{
    public function notifyQueued(ChatConversation $conversation): void;

    public function notifyCustomerMessage(ChatConversation $conversation, ChatMessage $message): void;
}
