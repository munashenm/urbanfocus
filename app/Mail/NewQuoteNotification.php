<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewQuoteNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Quote $quote) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New '.$this->quote->typeLabel().' — '.$this->quote->name,
            replyTo: [$this->quote->email],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.new-quote-admin',
        );
    }
}
