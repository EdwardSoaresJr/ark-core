<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Mail\OutboundTransactionalMail;
use App\Ark\Mail\TransactionalMailException;
use App\Ark\Mail\TransactionalMailOperation;
use App\Mail\DocumentCustomerMail;
use App\Models\User;
use Illuminate\Support\Str;

final class DocumentEmailDelivery
{
    public function __construct(
        private readonly DocumentAuthorize $authorize,
        private readonly RecordDocumentEventAction $documentEvents,
        private readonly ConversationRecorder $conversations,
        private readonly ConversationResolver $conversationResolver,
        private readonly ConversationParticipantResolver $participants,
        private readonly OutboundTransactionalMail $outboundMail,
    ) {}

    public function send(
        Document $document,
        User $actor,
        string $recipientEmail,
        ?string $staffNote = null,
    ): void {
        $document->loadMissing(['customer', 'repairOrder.vehicle']);

        $this->authorize->assertStoragePresent($document);

        $customer = $document->customer;
        abort_unless($customer !== null, 404);

        $recipientEmail = strtolower(trim($recipientEmail));
        abort_unless($recipientEmail !== '', 422, 'A recipient email is required.');

        $settings = ShopSettings::current();
        $shopName = $settings->shop_name ?: config('app.name', 'ARK-SMS');
        $attachmentFilename = $this->attachmentFilename($document);
        $note = filled($staffNote) ? trim($staffNote) : null;
        $repairOrder = $document->repairOrder;

        $mailable = new DocumentCustomerMail(
            customer: $customer,
            document: $document,
            shopName: $shopName,
            attachmentFilename: $attachmentFilename,
            repairOrder: $repairOrder,
            staffNote: $note,
        );

        $attachments = [];
        if (filled($document->storage_path)) {
            $full = storage_path('app/'.$document->storage_path);
            // local disk may store under storage/app/private or storage/app
            if (! is_file($full)) {
                $full = \Illuminate\Support\Facades\Storage::disk('local')->path($document->storage_path);
            }
            if (is_file($full)) {
                $attachments[] = [
                    'filename' => $attachmentFilename,
                    'mime' => $document->content_type ?: 'application/octet-stream',
                    'path' => $full,
                ];
            }
        }

        $mailResult = $this->outboundMail->sendMailable(
            TransactionalMailOperation::DocumentSend,
            $recipientEmail,
            $mailable,
            'document-'.$document->id.'-'.Str::uuid(),
            'document',
            (string) $document->id,
            $attachments,
        );

        if (! $mailResult->ok()) {
            throw new TransactionalMailException($mailResult);
        }

        $summary = sprintf(
            '%s emailed to %s.',
            $document->title !== '' ? $document->title : ($document->type?->label() ?? 'Document'),
            $recipientEmail,
        );

        if ($note !== null) {
            $summary .= ' Note: '.$note;
        }

        $this->documentEvents->handle($document, DocumentEventType::Emailed, $actor, [
            'channel' => 'email',
            'recipient_email' => $recipientEmail,
            'staff_note' => $note,
        ]);

        if ($repairOrder !== null) {
            $this->conversations->recordRepairOrderEmail(
                $repairOrder,
                $actor,
                $recipientEmail,
                $summary,
                metadata: [
                    'document_id' => $document->id,
                ],
            );

            return;
        }

        $conversation = $this->conversationResolver->forEmail($recipientEmail);
        $participant = $this->participants->system($conversation, displayName: 'Shop');

        $this->conversations->record(
            $conversation,
            $participant,
            OperationalCommunicationChannel::Email,
            OperationalCommunicationDirection::Outbound,
            $summary,
            metadata: [
                'actor_user_id' => $actor->id,
                'document_id' => $document->id,
                'customer_id' => $customer->id,
            ],
        );
    }

    private function attachmentFilename(Document $document): string
    {
        $original = trim((string) $document->original_name);

        if ($original !== '') {
            return $original;
        }

        $base = Str::slug($document->title !== '' ? $document->title : 'document') ?: 'document';
        $mime = strtolower($document->content_type);
        $extension = match (true) {
            $document->isPdf() => 'pdf',
            str_contains($mime, 'png') => 'png',
            str_contains($mime, 'jpeg') || str_contains($mime, 'jpg') => 'jpg',
            str_contains($mime, 'heic') => 'heic',
            str_contains($mime, 'heif') => 'heif',
            default => 'bin',
        };

        return $base.'.'.$extension;
    }
}
