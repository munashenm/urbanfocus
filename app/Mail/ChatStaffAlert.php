<?php

namespace App\Mail;

use App\Models\ChatConversation;
use App\Models\ChatMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChatStaffAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ChatConversation $conversation,
        public ChatMessage $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Customer chat — '.$this->conversation->topicLabel(),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.chat-staff-alert',
        );
    }
}
