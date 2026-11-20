<?php

namespace App\Mail;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChatAgentQueueAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ?ChatMessage $message = null,
    ) {}

    public function envelope(): Envelope
    {
        $subject = $this->message
            ? 'Urban Focus live chat: new customer message'
            : 'Urban Focus live chat: a customer is waiting';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.chat-agent-queue',
            with: [
                'conversation' => $this->conversation,
                'message' => $this->message,
                'url' => route('admin.live-chat.index'),
            ],
        );
    }
}
