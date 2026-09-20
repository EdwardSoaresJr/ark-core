<?php

namespace App\Mail;

use App\Ark\Operations\RepairOrders\EstimateTotals;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EstimateCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public RepairOrder $repairOrder,
        public EstimateTotals $totals,
        public string $shopName,
        public string $portalUrl,
        public string $pdfPath,
        public string $pdfFilename,
        public ?string $staffNote = null,
    ) {}

    public function envelope(): Envelope
    {
        $vehicle = $this->repairOrder->vehicle->display_name;

        return new Envelope(
            subject: sprintf('%s estimate for %s (RO #%d)', $this->shopName, $vehicle, $this->repairOrder->repair_order_id),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.estimate-customer',
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
