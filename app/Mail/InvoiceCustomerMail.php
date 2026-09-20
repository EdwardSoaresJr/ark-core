<?php

namespace App\Mail;

use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Support\Mail\ShopMailBranding;
use Brick\Money\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $balanceDue;

    public function __construct(
        public RepairOrder $repairOrder,
        public int $balanceDueCents,
        public string $shopName,
        public string $pdfPath,
        public string $pdfFilename,
        public ?string $staffNote = null,
        public ?string $payUrl = null,
    ) {
        $this->balanceDue = Money::ofMinor($balanceDueCents, 'USD')->formatTo('en_US');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            from: ShopMailBranding::from(),
            subject: ShopMailBranding::subject('Invoice'),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.invoice-customer',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->pdfPath)
                ->as($this->pdfFilename)
                ->withMime('application/pdf'),
        ];
    }
}
