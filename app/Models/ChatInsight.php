<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ChatInsight extends Model
{
    protected $fillable = [
        'conversation_id',
        'kind',
        'question',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(ChatConversation::class, 'conversation_id');
    }

    public static function record(?ChatConversation $conversation, string $kind, string $question): void
    {
        $question = trim($question);
        if ($question === '') {
            return;
        }

        static::query()->create([
            'conversation_id' => $conversation?->id,
            'kind' => $kind,
            'question' => Str::limit($question, 500, ''),
        ]);
    }
}
