<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Vehicles\Vehicle;
use Illuminate\Support\Collection;

final class RepairOrderPortalActivity
{
    /** @var list<string> */
    private const EVENT_NAMES = [
        OperationalEventName::PortalDocumentViewed->value,
        OperationalEventName::PortalDocumentDownloaded->value,
        OperationalEventName::PortalActiveVisitViewed->value,
        OperationalEventName::PortalVehicleViewed->value,
        OperationalEventName::PortalCommunicationSectionViewed->value,
    ];

    /**
     * @return Collection<int, OperationalEvent>
     */
    public function forRepairOrder(RepairOrder $repairOrder): Collection
    {
        $query = OperationalEvent::query()
            ->whereIn('event_name', self::EVENT_NAMES);

        if ($repairOrder->vehicle_id !== null) {
            $query->where('aggregate_type', Vehicle::class)
                ->where('aggregate_id', $repairOrder->vehicle_id);
        }

        return $query
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->filter(fn (OperationalEvent $event): bool => $this->matchesRepairOrder($event, $repairOrder))
            ->values();
    }

    public function countForRepairOrder(RepairOrder $repairOrder): int
    {
        return $this->forRepairOrder($repairOrder)->count();
    }

    public function label(OperationalEvent $event): string
    {
        return match (OperationalEventName::tryFrom($event->event_name)) {
            OperationalEventName::PortalVehicleViewed => 'Vehicle records opened',
            OperationalEventName::PortalActiveVisitViewed => 'Active visit reviewed',
            OperationalEventName::PortalDocumentViewed => 'Document viewed',
            OperationalEventName::PortalDocumentDownloaded => 'Document downloaded',
            OperationalEventName::PortalCommunicationSectionViewed => 'Portal communications reviewed',
            default => 'Portal activity',
        };
    }

    public function summary(OperationalEvent $event): string
    {
        $payload = $event->payload_json ?? [];

        return match (OperationalEventName::tryFrom($event->event_name)) {
            OperationalEventName::PortalVehicleViewed => sprintf(
                'Customer opened vehicle records%s.',
                ($payload['has_active_visit'] ?? false) ? ' with an active visit' : '',
            ),
            OperationalEventName::PortalActiveVisitViewed => 'Customer reviewed this repair order in vehicle records.',
            OperationalEventName::PortalDocumentViewed => filled($payload['document_number'] ?? null)
                ? 'Customer viewed '.$payload['document_number'].'.'
                : 'Customer viewed a portal document for this repair order.',
            OperationalEventName::PortalDocumentDownloaded => filled($payload['document_number'] ?? null)
                ? 'Customer downloaded '.$payload['document_number'].'.'
                : 'Customer downloaded a portal document for this repair order.',
            OperationalEventName::PortalCommunicationSectionViewed => 'Customer reviewed vehicle communication history in the portal.',
            default => 'Customer portal engagement recorded.',
        };
    }

    private function matchesRepairOrder(OperationalEvent $event, RepairOrder $repairOrder): bool
    {
        $payloadRepairOrderId = (int) ($event->payload_json['repair_order_id'] ?? 0);

        if ($payloadRepairOrderId > 0) {
            return $payloadRepairOrderId === $repairOrder->repair_order_id;
        }

        if (
            $repairOrder->vehicle_id !== null
            && $event->aggregate_type === Vehicle::class
            && (int) $event->aggregate_id === (int) $repairOrder->vehicle_id
            && in_array($event->event_name, [
                OperationalEventName::PortalVehicleViewed->value,
                OperationalEventName::PortalCommunicationSectionViewed->value,
            ], true)
        ) {
            return true;
        }

        return false;
    }
}
