<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Models\User;
use App\Services\Chat\ChatLiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Inbound agent actions for a later phone or notification channel.
 * Disabled until CHAT_AGENT_CHANNEL_TOKEN is set. This is not a customer channel.
 */
class ChatAgentChannelController extends Controller
{
    public function __construct(protected ChatLiveService $live) {}

    public function handle(Request $request): JsonResponse
    {
        $token = (string) config('chat.agent_channel.token');
        if ($token === '' || ! hash_equals($token, (string) $request->header('X-Chat-Channel-Token'))) {
            abort(404);
        }

        $validated = $request->validate([
            'conversation_id' => 'required|integer',
            'agent_email' => 'required|email',
            'action' => 'required|in:claim,reply,hand_back,close',
            'body' => 'nullable|string|max:5000',
        ]);

        $agent = User::query()->where('email', $validated['agent_email'])->where('is_active', true)->first();
        if (! $agent || ! $agent->hasPermission('chat.agent')) {
            abort(403);
        }

        $conversation = ChatConversation::query()->findOrFail($validated['conversation_id']);

        match ($validated['action']) {
            'claim' => $this->live->claim($conversation, $agent),
            'reply' => $this->live->reply($conversation, $agent, (string) ($validated['body'] ?? '')),
            'hand_back' => $this->live->handBack($conversation, $agent),
            'close' => $this->live->close($conversation, $agent),
        };

        return response()->json(['ok' => true, 'status' => $conversation->fresh()->status]);
    }
}
