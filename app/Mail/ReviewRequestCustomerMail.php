<?php

namespace App\Mail;

use App\Ark\Operations\Messaging\ReviewRequestCopy;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReviewRequestCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairOrder $repairOrder,
        public string $shopName,
        public string $reviewUrl,
        public string $contactUrl,
        public ?string $shopPhone = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: ShopMailBranding::from(),
            subject: ReviewRequestCopy::emailSubject($this->shopName),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.review-request-customer',
        );
    }
}
