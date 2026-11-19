<?php

namespace App\Mail;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ChatHandoverAlert extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, ChatMessage>  $recentMessages
     */
    public function __construct(
        public ChatConversation $conversation,
        public Collection $recentMessages,
    ) {}

    public function envelope(): Envelope
    {
        $who = $this->conversation->visitor_name ?: 'A visitor';

        return new Envelope(
            subject: 'Urban Focus chat: '.$who.' wants to talk to the team',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.chat-handover-alert',
        );
    }
}
