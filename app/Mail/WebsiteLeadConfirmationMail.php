<?php

namespace App\Mail;

use App\Support\Mail\ShopMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WebsiteLeadConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $shopName,
        public string $subjectLine,
        public string $intro,
        public ?string $responseHint = null,
        public ?string $phoneDisplay = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: ShopMailBranding::from(),
            subject: $this->subjectLine,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.website-lead-confirmation',
        );
    }
}
