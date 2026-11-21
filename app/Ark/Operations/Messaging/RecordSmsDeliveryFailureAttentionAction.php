<?php

namespace App\Ark\Operations\Messaging;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\Conversations\ConversationWork;
use App\Ark\Operations\Observations\OperationalObservation;
use App\Ark\Operations\Observations\OperationalObservationSeverity;
use App\Ark\Operations\Observations\OperationalObservationStream;
use App\Ark\Operations\Observations\OperationalObservationStreamEntry;
use App\Ark\Operations\Observations\OperationalObservationType;

/**
 * A text the shop sent did not arrive: put the conversation back in Needs attention and say so in the shared stream.
 * Delivery status callbacks repeat, so a message already recorded is left alone - including after staff resolved it.
 */
class RecordSmsDeliveryFailureAttentionAction
{
    public function __construct(
        private readonly ConversationWork $work,
        private readonly OperationalObservationStream $stream,
        private readonly SmsDeliveryFailureClassifier $failures,
    ) {}

    public function execute(string $messageSid, ?string $errorCode = null, ?string $recipientPhone = null): ?OperationalObservationStreamEntry
    {
        $message = ConversationMessage::query()
            ->where('metadata->twilio_message_sid', trim($messageSid))
            ->first();

        $conversation = $message?->conversation;

        if (! $message instanceof ConversationMessage || $conversation === null) {
            return null;
        }

        $dedupeKey = $this->stream->deliveryFailedDedupeKey($message->id);

        if (OperationalObservationStreamEntry::query()->where('dedupe_key', $dedupeKey)->exists()) {
            return null;
        }

        $errorCode = trim((string) $errorCode);
        $class = $this->failures->classify($errorCode !== '' ? $errorCode : null);

        $this->work->markNeedsAttention($conversation);

        return $this->stream->emit(
            new OperationalObservation(
                type: OperationalObservationType::CommunicationDeliveryFailed,
                severity: OperationalObservationSeverity::High,
                occurredAt: now(),
                customerId: $conversation->linkedCustomerId(),
                vehicleId: null,
                repairOrderId: null,
                conversationId: $conversation->id,
                headline: 'Text not delivered',
                description: $this->failures->staffGuidance($class, $errorCode !== '' ? $errorCode : null),
                metadata: [
                    'aggregate_type' => ConversationMessage::class,
                    'aggregate_id' => $message->id,
                    'conversation_message_id' => $message->id,
                    'error_code' => $errorCode !== '' ? $errorCode : null,
                    'failure_class' => $class->value,
                    'recipient' => $recipientPhone,
                    'deep_link' => 'conversation',
                ],
            ),
            $dedupeKey,
            'sms_delivery_failed',
        );
    }
}
