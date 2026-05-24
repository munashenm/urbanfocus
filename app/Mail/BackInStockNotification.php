<?php

namespace App\Mail;

use App\Models\Product;
use App\Models\StockAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BackInStockNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Product $product, public StockAlert $alert) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Back in stock: '.$this->product->name,
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.back-in-stock');
    }
}
