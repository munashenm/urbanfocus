<?php

namespace App\Http\Controllers;

use App\Models\ChatConversation;
use App\Services\Chat\ChatAvailability;
use App\Services\Chat\ChatPolicyGuide;
use App\Services\Chat\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function __construct(
        protected ChatService $chat,
        protected ChatAvailability $availability,
        protected ChatPolicyGuide $policies,
    ) {}

    public function session(): JsonResponse
    {
        $conversation = $this->chat->current();

        return $this->payload($conversation);
    }

    public function message(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
            'intent' => 'nullable|in:handover,product',
            'page_url' => 'nullable|string|max:500',
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:40',
        ]);

        $conversation = $this->chat->handleMessage(
            $validated['body'],
            $validated['intent'] ?? null,
            $validated['page_url'] ?? null,
            $validated['name'] ?? null,
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
        );

        return $this->payload($conversation);
    }

    public function handover(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page_url' => 'nullable|string|max:500',
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:40',
        ]);

        return $this->payload($this->chat->handover(
            $validated['page_url'] ?? null,
            $validated['name'] ?? null,
            $validated['email'] ?? null,
            $validated['phone'] ?? null,
        ));
    }

    public function track(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_number' => 'required|string|max:50',
            'email' => 'required|email|max:255',
        ]);

        return $this->payload($this->chat->track($validated['order_number'], $validated['email']));
    }

    public function request(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|in:return,warranty,support',
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:40',
            'order_number' => 'required_if:type,return,warranty|nullable|string|max:50',
            'product' => 'required_if:type,warranty|nullable|string|max:200',
            'serial_number' => 'required_if:type,warranty|nullable|string|max:120',
            'condition' => 'nullable|in:unused,opened,damaged,not_sure',
            'details' => 'required|string|max:2000',
            'page_url' => 'nullable|string|max:500',
        ]);

        return $this->payload($this->chat->request($validated));
    }

    protected function payload(?ChatConversation $conversation): JsonResponse
    {
        if ($conversation) {
            $conversation->loadMissing('assignedAgent');
        }

        $messages = $conversation
            ? $conversation->messages()->with('user')->orderBy('id')->get()->map->toPublicArray()->values()
            : collect();

        $open = $this->availability->isOpen();

        return response()->json([
            'availability' => $this->availability->snapshot(),
            'needs_contact' => ! $open && (
                ! $conversation
                || blank($conversation->visitor_name)
                || blank($conversation->visitor_email)
            ),
            'quick_help' => $this->quickHelp(),
            'policies' => [
                'returns' => $this->policies->returnsSummary(),
                'warranty' => $this->policies->warrantySummary(),
                'support' => $this->policies->supportSummary(),
            ],
            'messages' => $messages,
            'conversation' => [
                'status' => $conversation?->status,
                'agent_name' => $conversation?->assignedAgent?->name,
            ],
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    /**
     * @return list<array{label: string, url: string}>
     */
    protected function quickHelp(): array
    {
        return [
            ['label' => 'Search products', 'url' => route('shop.index')],
            ['label' => 'Request a quote', 'url' => route('b2b.quote')],
            ['label' => 'Track an order', 'url' => route('orders.track')],
            ['label' => 'Contact', 'url' => route('contact')],
        ];
    }
}
