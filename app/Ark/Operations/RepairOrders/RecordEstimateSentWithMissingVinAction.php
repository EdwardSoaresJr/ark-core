<?php

namespace App\Ark\Operations\RepairOrders;

use App\Ark\Operations\Events\OperationalEventName;
use App\Ark\Operations\Events\OperationalEventRecorder;
use App\Models\User;

class RecordEstimateSentWithMissingVinAction
{
    public function __construct(
        private readonly OperationalEventRecorder $events,
    ) {}

    public function record(RepairOrder $repairOrder, User $actor, string $channel): void
    {
        $repairOrder->loadMissing(['customer', 'vehicle']);

        $this->events->record(
            OperationalEventName::EstimateSentWithMissingVin,
            $repairOrder,
            actor: $actor,
            payload: [
                'repair_order_id' => $repairOrder->id,
                'vehicle_id' => $repairOrder->vehicle_id,
                'customer_id' => $repairOrder->customer_id,
                'channel' => $channel,
            ],
        );
    }
}
