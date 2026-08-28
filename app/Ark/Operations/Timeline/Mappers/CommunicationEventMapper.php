<?php

namespace App\Ark\Operations\Timeline\Mappers;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Timeline\OperationalEventEntry;
use App\Ark\Operations\Timeline\OperationalEventKind;
use App\Ark\Operations\Timeline\OperationalEventSource;
use App\Ark\Operations\Timeline\OperationalEventTone;

final class CommunicationEventMapper
{
    public function map(CommunicationEvent $event): OperationalEventEntry
    {
        $event->loadMissing(['repairOrder.vehicle', 'repairOrder.customer', 'creator']);

        $repairOrder = $event->repairOrder;
        $kind = $this->eventKind($event->event_type);
        $tone = match ($event->event_type) {
            OperationalCommunicationType::EstimateViewed,
            OperationalCommunicationType::CustomerReply => OperationalEventTone::Customer,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationType::InvoiceSent,
            OperationalCommunicationType::ApprovalFollowUp => OperationalEventTone::Shop,
            default => OperationalEventTone::Neutral,
        };

        return new OperationalEventEntry(
            source: OperationalEventSource::CommunicationEvent,
            kind: $kind,
            occurredAt: $event->occurred_at ?? $event->created_at ?? now(),
            headline: $event->event_type->label(),
            body: filled($event->summary) ? (string) $event->summary : null,
            actor: $event->creator?->name,
            tone: $tone,
            links: [],
            metadata: [
                'hub_filter' => $this->hubFilter($event->event_type),
                'timeline_category' => $this->timelineCategory($event->event_type),
                'communication_event_id' => $event->id,
                'repair_order_id' => $event->repair_order_id,
                'customer_id' => $repairOrder?->customer_id,
                'vehicle_id' => $repairOrder?->vehicle_id,
                'event_type' => $event->event_type->value,
                'channel_label' => $event->channel->label(),
            ],
            subject: $event,
        );
    }

    private function eventKind(OperationalCommunicationType $type): OperationalEventKind
    {
        return match ($type) {
            OperationalCommunicationType::EstimateViewed => OperationalEventKind::EstimateViewed,
            OperationalCommunicationType::EstimateSent => OperationalEventKind::EstimateSent,
            OperationalCommunicationType::InvoiceSent => OperationalEventKind::EstimateSent,
            OperationalCommunicationType::ApprovalFollowUp => OperationalEventKind::Approval,
            default => OperationalEventKind::Logged,
        };
    }

    private function hubFilter(OperationalCommunicationType $type): string
    {
        return match ($type) {
            OperationalCommunicationType::EstimateViewed,
            OperationalCommunicationType::EstimateSent,
            OperationalCommunicationType::InvoiceSent => 'portal',
            OperationalCommunicationType::ApprovalFollowUp => 'portal',
            default => 'logged',
        };
    }

    private function timelineCategory(OperationalCommunicationType $type): string
    {
        return match ($type) {
            OperationalCommunicationType::EstimateViewed,
            OperationalCommunicationType::EstimateSent => 'estimate',
            OperationalCommunicationType::InvoiceSent => 'payment',
            OperationalCommunicationType::ApprovalFollowUp => 'approval',
            default => 'logged',
        };
    }
}
