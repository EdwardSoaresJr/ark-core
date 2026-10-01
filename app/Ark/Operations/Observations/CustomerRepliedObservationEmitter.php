<?php

namespace App\Ark\Operations\Observations;

use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Customers\Customer;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Ark\Operations\Timeline\Mappers\ConversationMessageEventMapper;
use App\Models\User;

/**
 * Authority event → customer_replied observation → shared stream.
 */
final class CustomerRepliedObservationEmitter
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
        private readonly ConversationMessageEventMapper $messageMapper,
        private readonly OperationalObservationStream $stream,
    ) {}

    public function emitFromInboundMessage(ConversationMessage $message, ?int $customerId): ?OperationalObservationStreamEntry
    {
        if ($message->direction !== OperationalCommunicationDirection::Inbound) {
            return null;
        }

        $metadata = is_array($message->metadata) ? $message->metadata : [];

        if (($metadata['portal_estimate_view'] ?? false) === true
            || ($metadata['website_lead'] ?? false) === true) {
            return null;
        }

        $message->loadMissing(['conversation', 'participant.customer']);

        $resolvedCustomerId = $customerId
            ?? $message->participant?->customer_id
            ?? $this->customerIdFromParticipant($message);

        $customer = $resolvedCustomerId !== null
            ? Customer::query()->find($resolvedCustomerId)
            : null;

        $firstName = trim((string) ($customer?->first_name ?? ''));
        $headline = $firstName !== '' ? "{$firstName} replied" : 'Customer replied';
        $age = $message->occurred_at?->diffForHumans(short: true) ?? 'just now';
        $preview = trim((string) $message->body);
        $description = $preview !== ''
            ? mb_strlen($preview) > 120 ? mb_substr($preview, 0, 117).'…' : $preview
            : "Customer replied {$age}";

        $timelineEntry = $this->messageMapper->map($message);

        $this->events->record(
            OperationalEventName::ConversationMessageReceived,
            $message,
            payload: [
                'direction' => OperationalCommunicationDirection::Inbound->value,
                'conversation_id' => $message->conversation_id,
                'customer_id' => $resolvedCustomerId,
                'channel' => $message->channel->value,
            ],
        );

        $observation = new OperationalObservation(
            type: OperationalObservationType::CustomerReplied,
            severity: OperationalObservationSeverity::Medium,
            occurredAt: $message->occurred_at ?? now(),
            customerId: $resolvedCustomerId,
            vehicleId: null,
            repairOrderId: null,
            conversationId: $message->conversation_id,
            headline: $headline,
            description: $description,
            sourceEvents: [OperationalObservationSourceEvent::fromEntry($timelineEntry)],
            metadata: [
                'aggregate_type' => ConversationMessage::class,
                'aggregate_id' => $message->id,
                'conversation_message_id' => $message->id,
            ],
        );

        return $this->stream->emit(
            $observation,
            $this->stream->customerRepliedDedupeKey($message->conversation_id),
            OperationalEventName::ConversationMessageReceived->value,
        );
    }

    public function resolveAfterShopReply(ConversationMessage $message, ?User $actor = null): void
    {
        if ($message->direction !== OperationalCommunicationDirection::Outbound) {
            return;
        }

        $this->stream->resolveCustomerRepliedForConversation($message->conversation_id, $actor);
    }

    private function customerIdFromParticipant(ConversationMessage $message): ?int
    {
        return $message->participant?->customer_id;
    }
}
