<?php

namespace App\Mail;

use App\Support\Mail\ShopMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BookIdentityCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $shopName,
        public string $plainCode,
        public int $ttlMinutes = 5,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: ShopMailBranding::from(),
            subject: ShopMailBranding::subject('Verification Code'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.book-identity-code',
        );
    }
}
