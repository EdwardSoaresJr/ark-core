<?php

namespace App\Mail;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairOrder $repairOrder,
        public string $shopName,
        public string $portalUrl,
        public string $balanceDueDisplay,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: ShopMailBranding::from(),
            subject: ShopMailBranding::subject('Pay Link'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-payment-customer',
        );
    }
}
