<?php

namespace App\Ark\Operations\Workboard;

final class WorkboardInventoryContext
{
    public static function isWorkboardQueue(
        ?string $pickupFilter,
        ?string $laneFilter,
        ?string $attentionFilter,
        bool $unassignedFilter,
    ): bool {
        return $pickupFilter !== null
            || $laneFilter !== null
            || $attentionFilter !== null
            || $unassignedFilter;
    }

    public static function returnUrl(
        ?string $pickupFilter,
        ?string $laneFilter,
        ?string $attentionFilter,
        bool $unassignedFilter,
    ): ?string {
        if ($attentionFilter === 'customer_waiting') {
            return route('operations.index', ['queue' => 'customer_waiting']);
        }

        if ($attentionFilter === 'needs_attention') {
            return route('operations.index', ['queue' => WorkboardQueueCatalog::NEEDS_ATTENTION_QUEUE]);
        }

        if ($unassignedFilter) {
            return route('operations.index', ['queue' => 'unassigned']);
        }

        if ($pickupFilter === 'stale') {
            return route('operations.index', ['queue' => 'overdue_pickup']);
        }

        if ($pickupFilter === 'all') {
            return route('operations.index', ['queue' => 'ready_pickup']);
        }

        if ($laneFilter !== null && $laneFilter !== '') {
            return route('operations.index', ['queue' => $laneFilter]);
        }

        return null;
    }
}
