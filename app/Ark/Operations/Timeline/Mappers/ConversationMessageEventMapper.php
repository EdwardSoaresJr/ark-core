<?php

namespace App\Ark\Operations\Timeline\Mappers;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Documents\Document;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;
use App\Ark\Runtime\Authorization\ArkCapability;
use App\Models\User;

final class ConversationMessageEventMapper
{
    /** @var array<int, string|null> */
    private array $actorNames = [];

    public function map(ConversationMessage $message): OperationalEventEntry
    {
        $message->loadMissing(['participant.user', 'participant.customer', 'attachments']);

        $isInternal = $message->direction === OperationalCommunicationDirection::Internal
            || $message->channel === OperationalCommunicationChannel::Internal;
        $isCallNote = (bool) ($message->metadata['call_note'] ?? false);

        [$kind, $hubFilter] = $isInternal
            ? [OperationalEventKind::InternalNote, 'logged']
            : $this->channelKind($message->channel);

        $tone = match (true) {
            $isInternal => OperationalEventTone::Internal,
            $message->direction === OperationalCommunicationDirection::Inbound => OperationalEventTone::Customer,
            $message->direction === OperationalCommunicationDirection::Outbound => OperationalEventTone::Shop,
            default => OperationalEventTone::Neutral,
        };

        $headline = match (true) {
            $isCallNote => 'Call note',
            $isInternal => 'Internal note',
            default => $message->direction->queueLabel(),
        };

        $hasAttachments = $message->attachments->isNotEmpty();
        $channelLabel = match (true) {
            $message->channel === OperationalCommunicationChannel::Sms && $hasAttachments => 'MMS',
            default => $message->channel->label(),
        };

        return new OperationalEventEntry(
            source: OperationalEventSource::ConversationMessage,
            kind: $kind,
            occurredAt: $message->occurred_at ?? $message->created_at ?? now(),
            headline: $headline,
            body: filled($message->body) && $message->body !== '(attachment)'
                ? (string) $message->body
                : null,
            actor: $this->actorLabel($message),
            tone: $tone,
            links: $this->documentLinks($message),
            metadata: [
                'recipient_email' => $message->direction === OperationalCommunicationDirection::Outbound
                    ? ($message->metadata['recipient_email'] ?? null)
                    : null,
                'document_label' => $message->metadata['document_label'] ?? null,
                'hub_filter' => $hubFilter,
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'channel_label' => $channelLabel,
                'direction' => $message->direction->value,
                'call_note' => $isCallNote,
                'imported_label' => filled($message->metadata['backfilled_at'] ?? null)
                    ? 'Imported history'.((int) ($message->metadata['media_count'] ?? 0) > 0 && $message->attachments->isEmpty() ? ' · photo not recovered' : '')
                    : null,
                'attachment_count' => $message->attachments->count(),
                'attachments' => $message->attachments
                    ->map(fn ($attachment): array => [
                        'id' => $attachment->id,
                        'content_type' => $attachment->content_type,
                        'byte_size' => $attachment->byte_size,
                        'is_image' => $attachment->isImage(),
                        'is_video' => $attachment->isVideo(),
                        'is_audio' => $attachment->isAudio(),
                        'is_pdf' => $attachment->isPdf(),
                    ])
                    ->values()
                    ->all(),
            ],
            subject: $message,
        );
    }

    /**
     * @return array{0: OperationalEventKind, 1: string}
     */
    private function channelKind(OperationalCommunicationChannel $channel): array
    {
        return match ($channel) {
            OperationalCommunicationChannel::Sms => [OperationalEventKind::Sms, 'text'],
            OperationalCommunicationChannel::Email => [OperationalEventKind::Email, 'email'],
            OperationalCommunicationChannel::Messenger => [OperationalEventKind::Messenger, 'messenger'],
            OperationalCommunicationChannel::Website => [OperationalEventKind::Portal, 'portal'],
            OperationalCommunicationChannel::Phone => [OperationalEventKind::Logged, 'logged'],
            default => [OperationalEventKind::Logged, 'logged'],
        };
    }

    private function actorLabel(ConversationMessage $message): ?string
    {
        $participant = $message->participant;

        if ($participant === null) {
            return null;
        }

        return $participant->user?->name
            ?? $participant->customer?->name
            ?? $this->actorName($message->metadata['actor_user_id'] ?? null);
    }

    /**
     * The staff member who sent a message the shop recorded on their behalf, such as an emailed document.
     */
    private function actorName(mixed $userId): ?string
    {
        if (! is_numeric($userId)) {
            return null;
        }

        $userId = (int) $userId;

        return $this->actorNames[$userId] ??= User::query()->whereKey($userId)->value('name');
    }

    /**
     * The emailed document, for staff allowed to open it.
     *
     * @return array<string, string>
     */
    private function documentLinks(ConversationMessage $message): array
    {
        $metadata = $message->metadata ?? [];
        $viewer = auth()->user();

        if (! $viewer instanceof User) {
            return [];
        }

        $label = 'Open '.strtolower((string) ($metadata['document_label'] ?? 'document'));

        if (is_numeric($metadata['estimate_document_id'] ?? null) && is_numeric($metadata['repair_order_id'] ?? null)
            && $viewer->can(ArkCapability::RepairOrdersView->value)) {
            return [$label => route('operations.repair-orders.estimate-documents.show', [
                'repairOrder' => (int) $metadata['repair_order_id'],
                'document' => (int) $metadata['estimate_document_id'],
            ])];
        }

        if (is_numeric($metadata['document_id'] ?? null)
            && ($viewer->can(ArkCapability::CustomersManage->value) || $viewer->can(ArkCapability::RepairOrdersView->value))) {
            $customerId = Document::query()->whereKey((int) $metadata['document_id'])->value('customer_id');

            if ($customerId !== null) {
                return [$label => route('operations.customers.documents.show', [
                    'customer' => (int) $customerId,
                    'document' => (int) $metadata['document_id'],
                ])];
            }
        }

        return [];
    }
}
