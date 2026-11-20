<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Services\Chat\ChatLiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LiveChatController extends Controller
{
    public function __construct(protected ChatLiveService $live) {}

    public function index(Request $request): View
    {
        return view('admin.live-chat.index', [
            'presence' => $this->live->presenceFor($request->user()),
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $this->live->presenceFor($request->user())->forceFill(['last_seen_at' => now()])->save();

        return response()->json([
            'presence' => $this->live->presenceFor($request->user())->status,
            'queue' => $this->cards($this->queueQuery()->limit(30)->get()),
            'active' => $this->cards(
                ChatConversation::query()
                    ->with('latestMessage')
                    ->where('status', 'live')
                    ->where('assigned_user_id', $request->user()->id)
                    ->orderByDesc('last_message_at')
                    ->limit(30)
                    ->get()
            ),
            'queue_count' => $this->queueQuery()->count(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function transcript(ChatConversation $conversation): JsonResponse
    {
        $conversation->load(['messages.user', 'assignedAgent']);
        if ($conversation->assigned_user_id === null || (int) $conversation->assigned_user_id === (int) auth()->id()) {
            $conversation->forceFill(['staff_read_at' => now()])->save();
        }

        return response()->json([
            'conversation' => $this->card($conversation),
            'messages' => $conversation->messages->map(fn ($message) => [
                'id' => $message->id,
                'sender' => $message->sender,
                'sender_name' => $message->publicSenderName(),
                'body' => $message->body,
                'at' => $message->created_at?->timezone(config('chat.timezone'))->format('H:i'),
            ])->values(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function presence(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:online,away',
        ]);
        $presence = $this->live->setPresence($request->user(), $validated['status']);

        return response()->json(['status' => $presence->status]);
    }

    public function claim(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->live->claim($conversation, $request->user());

        return $this->transcript($conversation->fresh());
    }

    public function reply(Request $request, ChatConversation $conversation): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);
        $this->live->reply($conversation, $request->user(), $validated['body']);

        return $this->transcript($conversation->fresh());
    }

    public function handBack(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->live->handBack($conversation, $request->user());

        return $this->transcript($conversation->fresh());
    }

    public function close(Request $request, ChatConversation $conversation): JsonResponse
    {
        $this->live->close($conversation, $request->user());

        return $this->transcript($conversation->fresh());
    }

    protected function queueQuery()
    {
        return ChatConversation::query()
            ->with('latestMessage')
            ->where('status', 'waiting_staff')
            ->where('bot_paused', true)
            ->whereNull('assigned_user_id')
            ->orderBy('last_message_at');
    }

    /**
     * @param  iterable<ChatConversation>  $conversations
     * @return list<array<string, mixed>>
     */
    protected function cards(iterable $conversations): array
    {
        return collect($conversations)->map(fn (ChatConversation $conversation) => $this->card($conversation))->all();
    }

    /**
     * @return array<string, mixed>
     */
    protected function card(ChatConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'customer' => $conversation->visitor_name ?: 'Website visitor',
            'email' => $conversation->visitor_email,
            'preview' => $conversation->latestMessage?->body,
            'at' => $conversation->last_message_at?->timezone(config('chat.timezone'))->format('d M H:i'),
            'agent' => $conversation->assignedAgent?->name,
        ];
    }
}
