<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ChatConversation extends Model
{
    protected $fillable = [
        'public_token',
        'status',
        'topic',
        'visitor_name',
        'visitor_email',
        'visitor_phone',
        'page_url',
        'staff_read_at',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'staff_read_at' => 'datetime',
            'last_message_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'conversation_id');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(ChatMessage::class, 'conversation_id')->latestOfMany();
    }

    public function topicLabel(): string
    {
        return match ($this->topic) {
            'warranty' => 'Warranty',
            'return' => 'Return',
            'support' => 'Support',
            'tracking' => 'Order tracking',
            'product' => 'Product',
            default => 'General',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'waiting_staff' => 'Waiting for staff',
            'closed' => 'Closed',
            default => 'Open',
        };
    }

    public function needsStaffAttention(): bool
    {
        if ($this->status === 'closed' || $this->last_message_at === null) {
            return false;
        }

        return $this->staff_read_at === null || $this->last_message_at->gt($this->staff_read_at);
    }
}
