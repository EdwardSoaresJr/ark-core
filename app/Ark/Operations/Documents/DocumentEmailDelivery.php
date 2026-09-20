<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Mail\DocumentCustomerMail;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

final class DocumentEmailDelivery
{
    public function __construct(
        private readonly DocumentAuthorize $authorize,
        private readonly RecordDocumentEventAction $documentEvents,
        private readonly ConversationRecorder $conversations,
        private readonly ConversationResolver $conversationResolver,
        private readonly ConversationParticipantResolver $participants,
        private readonly ArkMailClient $mail,
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

        if (ManagedMailGate::platformSend()) {
            $this->sendViaPlatform($mailable, $document, $recipientEmail, $attachmentFilename);
        } else {
            Mail::to($recipientEmail)->send($mailable);
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

    private function sendViaPlatform(
        DocumentCustomerMail $mailable,
        Document $document,
        string $recipientEmail,
        string $attachmentFilename,
    ): void {
        $bytes = Storage::disk('local')->get($document->storage_path);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('This document could not be attached to the email.');
        }

        $result = $this->mail->sendTransactional([
            'operation' => 'document.send',
            'to' => $recipientEmail,
            'subject' => (string) $mailable->envelope()->subject,
            'html_body' => $mailable->render(),
            'attachments' => [[
                'filename' => $attachmentFilename,
                'mime' => $document->content_type ?: 'application/octet-stream',
                'content_base64' => base64_encode($bytes),
            ]],
            'idempotency_key' => 'document-send-'.Str::uuid(),
            'domain_object_type' => 'document',
            'domain_object_id' => (string) $document->id,
            'metadata' => array_filter([
                'document_id' => $document->id,
                'repair_order_id' => $document->repair_order_id,
            ]),
        ]);

        if (($result['ok'] ?? false) !== true) {
            throw new RuntimeException(
                is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Document email could not be sent.',
            );
        }
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
