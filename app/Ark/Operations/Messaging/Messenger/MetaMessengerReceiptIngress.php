<?php

namespace App\Ark\Operations\Messaging\Messenger;

use App\Ark\Operations\Communications\CommunicationEventRecorder;
use App\Ark\Operations\Communications\OperationalCommunicationChannel;
use App\Ark\Operations\Communications\OperationalCommunicationDirection;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Conversations\Conversation;
use App\Ark\Operations\Conversations\ConversationContactSurface;
use App\Ark\Operations\Conversations\ConversationLink;
use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use Illuminate\Support\Carbon;

class MetaMessengerReceiptIngress
{
    public function __construct(
        private readonly CommunicationEventRecorder $events,
    ) {}

    public function ingest(MetaMessengerReceiptPayload $payload): void
    {
        if ($payload->isDelivery()) {
            foreach ($payload->messageIds as $messageId) {
                $this->recordDelivery($messageId);
            }

            return;
        }

        if ($payload->isRead()) {
            $this->recordRead($payload->psid, $payload->watermark);
        }
    }

    private function recordDelivery(string $providerMessageId): void
    {
        $message = $this->findOutboundMessage($providerMessageId);

        if ($message === null) {
            return;
        }

        foreach ($this->linkedRepairOrders($message) as $repairOrder) {
            $exists = $repairOrder->communicationEvents()
                ->where('conversation_message_id', $message->id)
                ->where('event_type', OperationalCommunicationType::MessageDelivered)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->events->record(
                repairOrder: $repairOrder,
                eventType: OperationalCommunicationType::MessageDelivered,
                channel: OperationalCommunicationChannel::Messenger,
                direction: OperationalCommunicationDirection::Outbound,
                summary: 'Messenger message delivered',
                message: $message,
            );
        }
    }

    private function recordRead(string $psid, ?int $watermark): void
    {
        if ($watermark === null) {
            return;
        }

        $cacheKey = 'messenger:read:'.$psid.':'.$watermark;

        if (cache()->has($cacheKey)) {
            return;
        }

        $conversationIds = Conversation::query()
            ->where('contact_surface', ConversationContactSurface::Messenger)
            ->where('contact_address', $psid)
            ->pluck('id');

        if ($conversationIds->isEmpty()) {
            return;
        }

        $watermarkAt = Carbon::createFromTimestampMs($watermark);

        $message = ConversationMessage::query()
            ->whereIn('conversation_id', $conversationIds)
            ->where('channel', OperationalCommunicationChannel::Messenger)
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->where('occurred_at', '<=', $watermarkAt)
            ->latest('occurred_at')
            ->latest('id')
            ->first();

        if ($message === null) {
            return;
        }

        foreach ($this->linkedRepairOrders($message) as $repairOrder) {
            $exists = $repairOrder->communicationEvents()
                ->where('conversation_message_id', $message->id)
                ->where('event_type', OperationalCommunicationType::MessageRead)
                ->exists();

            if ($exists) {
                continue;
            }

            $this->events->record(
                repairOrder: $repairOrder,
                eventType: OperationalCommunicationType::MessageRead,
                channel: OperationalCommunicationChannel::Messenger,
                direction: OperationalCommunicationDirection::Outbound,
                summary: 'Messenger message read',
                message: $message,
                occurredAt: $watermarkAt,
            );
        }

        cache()->put($cacheKey, true, now()->addDays(30));
    }

    private function findOutboundMessage(string $providerMessageId): ?ConversationMessage
    {
        return ConversationMessage::query()
            ->where('direction', OperationalCommunicationDirection::Outbound)
            ->where(function ($query) use ($providerMessageId): void {
                $query->where('metadata->provider_message_id', $providerMessageId)
                    ->orWhere('metadata->meta_message_id', $providerMessageId);
            })
            ->first();
    }

    /**
     * @return list<RepairOrder>
     */
    private function linkedRepairOrders(ConversationMessage $message): array
    {
        $repairOrderIds = ConversationLink::query()
            ->where('conversation_id', $message->conversation_id)
            ->where('linkable_type', RepairOrder::class)
            ->pluck('linkable_id')
            ->unique()
            ->values()
            ->all();

        if ($repairOrderIds === []) {
            return [];
        }

        return RepairOrder::query()
            ->whereIn('id', $repairOrderIds)
            ->get()
            ->all();
    }
}
