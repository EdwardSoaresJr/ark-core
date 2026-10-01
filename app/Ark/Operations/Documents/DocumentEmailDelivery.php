<?php

namespace App\Ark\Operations\Documents;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationParticipantResolver;
use App\Ark\Operations\Conversations\ConversationRecorder;
use App\Ark\Operations\Conversations\ConversationResolver;
use App\Ark\Operations\Settings\ShopSettings;
use App\Ark\Mail\EmailIntent;
use App\Ark\Platform\Mail\ArkMailClient;
use App\Ark\Platform\Mail\ManagedMailGate;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Illuminate\Support\Str;

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

        if (! ManagedMailGate::platformSend()) {
            throw new RuntimeException("Email isn't configured yet.");
        }

        $variables = [
            'shop_name' => $shopName,
            'document_label' => $document->type?->label() ?? 'Document',
            'document_title' => $document->title !== '' ? $document->title : ($document->type?->label() ?? 'Document'),
        ];
        if ($repairOrder !== null) {
            $variables['repair_order_number'] = (string) $repairOrder->repair_order_id;
            if ($repairOrder->vehicle !== null) {
                $variables['vehicle'] = (string) $repairOrder->vehicle->display_name;
            }
        }
        if ($note !== null) {
            $variables['staff_note'] = $note;
        }

        $this->sendViaPlatform($document, $recipientEmail, $attachmentFilename, $variables);

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

    /**
     * @param  array<string, string>  $variables
     */
    private function sendViaPlatform(
        Document $document,
        string $recipientEmail,
        string $attachmentFilename,
        array $variables,
    ): void {
        $bytes = Storage::disk('local')->get($document->storage_path);

        if (! is_string($bytes) || $bytes === '') {
            throw new RuntimeException('This document could not be attached to the email.');
        }

        $result = $this->mail->sendTransactional(EmailIntent::payload(
            operation: 'document.send',
            to: $recipientEmail,
            variables: $variables,
            idempotencyKey: 'document-send-'.Str::uuid(),
            domainObjectType: 'document',
            domainObjectId: (string) $document->id,
            attachments: [[
                'filename' => $attachmentFilename,
                'mime' => $document->content_type ?: 'application/octet-stream',
                'content_base64' => base64_encode($bytes),
            ]],
            metadata: array_filter([
                'document_id' => $document->id,
                'repair_order_id' => $document->repair_order_id,
            ]),
        ));

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
