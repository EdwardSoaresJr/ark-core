<?php

namespace App\Ark\Operations\Communications;

use App\Ark\Operations\Conversations\ConversationMessage;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Models\User;
use Illuminate\Support\Carbon;

class CommunicationEventRecorder
{
    public function record(
        RepairOrder $repairOrder,
        OperationalCommunicationType $eventType,
        OperationalCommunicationChannel $channel,
        OperationalCommunicationDirection $direction,
        string $summary,
        ?User $actor = null,
        ?ConversationMessage $message = null,
        ?Carbon $occurredAt = null,
    ): CommunicationEvent {
        return $repairOrder->communicationEvents()->create([
            'created_by' => $actor?->id,
            'event_type' => $eventType,
            'channel' => $channel,
            'direction' => $direction,
            'summary' => trim($summary),
            'conversation_message_id' => $message?->id,
            'occurred_at' => $occurredAt ?? now(),
        ]);
    }
}
