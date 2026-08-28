<?php

namespace App\Ark\Operations\Timeline\Mappers;

use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;

final class ConversationMessageEventMapper
{
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
            links: [],
            metadata: [
                'hub_filter' => $hubFilter,
                'message_id' => $message->id,
                'conversation_id' => $message->conversation_id,
                'channel_label' => $channelLabel,
                'direction' => $message->direction->value,
                'call_note' => $isCallNote,
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
            ?? null;
    }
}
