<?php

namespace App\Ark\Operations\Portal;

use App\Ark\Operations\Communications\CommunicationEvent;
use App\Ark\Operations\Communications\OperationalCommunicationType;
use App\Ark\Operations\Documents\EstimateDocument;
use App\Ark\Operations\RepairOrders\RepairOrder;
use App\Ark\Operations\Settings\ShopDisplayTimezone;
use Carbon\CarbonInterface;

final class PortalEstimatePreparedOn
{
    public function label(RepairOrder $repairOrder): ?string
    {
        return ShopDisplayTimezone::formatDate($this->moment($repairOrder));
    }

    public function moment(RepairOrder $repairOrder): ?CarbonInterface
    {
        $repairOrder->loadMissing('communicationEvents');

        $firstSent = $repairOrder->communicationEvents
            ->filter(fn (CommunicationEvent $event): bool => $event->event_type === OperationalCommunicationType::EstimateSent)
            ->sortBy(fn (CommunicationEvent $event): int => $event->occurred_at?->getTimestamp() ?? PHP_INT_MAX)
            ->first()
            ?->occurred_at;

        if ($firstSent !== null) {
            return $firstSent;
        }

        $documentCreatedAt = EstimateDocument::query()
            ->where('repair_order_id', $repairOrder->id)
            ->where('document_type', 'estimate')
            ->orderBy('id')
            ->value('created_at');

        return $documentCreatedAt instanceof CarbonInterface ? $documentCreatedAt : null;
    }
}
