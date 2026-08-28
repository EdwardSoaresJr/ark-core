<?php

namespace App\Mail;

use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Documents\Document;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Support\Mail\ShopMailBranding;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DocumentCustomerMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Customer $customer,
        public Document $document,
        public string $shopName,
        public string $attachmentFilename,
        public ?RepairOrder $repairOrder = null,
        public ?string $staffNote = null,
    ) {}

    public function envelope(): Envelope
    {
        $typeLabel = $this->document->type?->label() ?? 'Document';

        return new Envelope(
            from: ShopMailBranding::from(),
            subject: ShopMailBranding::subject($typeLabel),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.document-customer',
        );
    }

    /**
     * @return list<Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->document->storage_path)
                ->as($this->attachmentFilename)
                ->withMime($this->document->content_type ?: 'application/octet-stream'),
        ];
    }
}
