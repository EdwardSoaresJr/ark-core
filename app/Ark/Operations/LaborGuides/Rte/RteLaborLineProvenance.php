<?php

namespace App\Ark\Operations\LaborGuides\Rte;

use App\Ark\Operations\Events\OperationalEvent;
use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\RepairOrders\RepairOrder;

/**
 * Links estimate lines back to RTE apply facts for observation.
 */
final class RteLaborLineProvenance
{
    public function isRteLaborLine(int $lineId): bool
    {
        return $this->rteLineAddedEvent($lineId) !== null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function rteLineAddedPayload(int $lineId): ?array
    {
        $event = $this->rteLineAddedEvent($lineId);

        return is_array($event?->payload_json) ? $event->payload_json : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function recommendationAppliedPayload(RepairOrder $repairOrder, int $lineId): ?array
    {
        $events = OperationalEvent::query()
            ->where('aggregate_type', RepairOrder::class)
            ->where('aggregate_id', $repairOrder->id)
            ->where('event_name', OperationalEventName::RteRecommendationApplied->value)
            ->orderByDesc('id')
            ->get();

        foreach ($events as $event) {
            $payload = $event->payload_json;

            if (! is_array($payload)) {
                continue;
            }

            $lineIds = $payload['line_ids'] ?? [];

            if (is_array($lineIds) && in_array($lineId, $lineIds, true)) {
                return $payload;
            }
        }

        return null;
    }

    private function rteLineAddedEvent(int $lineId): ?OperationalEvent
    {
        return OperationalEvent::query()
            ->where('event_name', OperationalEventName::EstimateLineAdded->value)
            ->where('payload_json->line_id', $lineId)
            ->where('payload_json->source', 'rte_labor_guide')
            ->orderByDesc('id')
            ->first();
    }
}
