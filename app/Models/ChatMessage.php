<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'conversation_id',
        'user_id',
        'sender',
        'kind',
        'body',
        'cards',
        'payload',
        'review_status',
    ];

    protected function casts(): array
    {
        return [
            'cards' => 'array',
            'payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function publicSenderName(): string
    {
        return match ($this->sender) {
            'visitor' => 'You',
            'staff' => $this->user?->name ?: 'Urban Focus team',
            'system' => 'Urban Focus',
            default => 'Urban Focus',
        };
    }

    public function senderLabel(): string
    {
        return match ($this->sender) {
            'visitor' => 'Customer',
            'staff' => 'Staff',
            'system' => 'Notice',
            default => 'Product assistant',
        };
    }

    /**
     * Fields safe to return to the browser that owns this conversation.
     * Structured request details (email, serial, phone) stay in payload
     * for staff and are not included here.
     *
     * @return array<string, mixed>
     */
    public function toPublicArray(): array
    {
        return [
            'id' => $this->id,
            'sender' => $this->sender,
            'kind' => $this->kind,
            'body' => $this->body,
            'cards' => $this->cards ?? [],
            'review_status' => $this->review_status,
            'sender_name' => $this->publicSenderName(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
