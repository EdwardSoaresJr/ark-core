<?php

namespace App\Mail;

use App\Support\Mail\ShopMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalAccessCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $shopName,
        public string $plainCode,
        public string $customerFirstName = '',
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: ShopMailBranding::from(),
            subject: ShopMailBranding::subject('Access Code'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.portal-access-code',
        );
    }
}
