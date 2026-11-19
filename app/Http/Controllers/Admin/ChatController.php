<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
use App\Services\Chat\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function __construct(protected ChatService $chat) {}

    public function index(Request $request): View
    {
        $query = ChatConversation::query()->with('latestMessage');

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        if ($topic = $request->string('topic')->toString()) {
            $query->where('topic', $topic);
        }

        $conversations = $query->orderByDesc('last_message_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return view('admin.chat.index', compact('conversations'));
    }

    public function show(ChatConversation $conversation): View
    {
        $conversation->load(['messages.user']);
        $conversation->forceFill(['staff_read_at' => now()])->save();

        return view('admin.chat.show', compact('conversation'));
    }

    public function reply(Request $request, ChatConversation $conversation): RedirectResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $this->chat->staffReply($conversation, $validated['body'], (int) $request->user()->id);

        return redirect()
            ->route('admin.chat.show', $conversation)
            ->with('success', 'Reply sent to the customer.');
    }

    public function close(ChatConversation $conversation): RedirectResponse
    {
        $conversation->update(['status' => 'closed', 'staff_read_at' => now()]);

        return back()->with('success', 'Conversation closed. A new customer message will open it again.');
    }

    public function destroy(ChatConversation $conversation): RedirectResponse
    {
        $conversation->delete();

        return redirect()
            ->route('admin.chat.index')
            ->with('success', 'Conversation deleted.');
    }

    public function updateRequest(Request $request, ChatConversation $conversation, ChatMessage $message): RedirectResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->review_status !== null, 404);

        $validated = $request->validate([
            'review_status' => 'required|in:submitted,in_review,closed',
        ]);

        $message->update(['review_status' => $validated['review_status']]);

        return back()->with('success', 'Request status updated. This does not approve a return, refund or warranty claim.');
    }
}
